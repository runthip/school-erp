<?php
namespace App\Models;
use App\Core\Model;

/** โมเดลรวมฝ่ายบริหาร: KPI / อนุมัติ / หนังสือราชการ / E-Office / ปฏิทิน */
class Admin extends Model
{
    protected string $table = '';

    // ---------- KPI ----------
    public function kpis(string $level=''): array
    {
        $w=''; $p=[];
        if($level!==''){ $w='WHERE education_level=?'; $p[]=$level; }
        return $this->query("SELECT * FROM kpis $w ORDER BY education_level, category, id", $p);
    }
    public function kpiFind(int $id): ?array { return $this->first("SELECT * FROM kpis WHERE id=?", [$id]); }
    public function kpiCreate(array $d): int
    {
        $this->execute("INSERT INTO kpis (school_id, academic_year_id, education_level, category, name, unit, target_value, actual_value, direction, updated_by)
            VALUES (1,(SELECT id FROM academic_years WHERE is_current=1 LIMIT 1),?,?,?,?,?,?,?,?)",
            [$d['education_level'],$d['category'],$d['name'],$d['unit'],$d['target_value'],$d['actual_value'],$d['direction'],$d['updated_by']]);
        return $this->lastId();
    }
    public function kpiDelete(int $id): void
    { $this->execute("DELETE FROM kpis WHERE id=?", [$id]); }
    public function kpiUpdateActual(int $id, float $actual, ?int $by): void
    { $this->execute("UPDATE kpis SET actual_value=?, updated_by=? WHERE id=?", [$actual,$by,$id]); }

    // ---------- คำขออนุมัติ ----------
    public function requestsForApprover(int $userId, string $status=''): array
    {
        $w='WHERE ar.approver_id=?'; $p=[$userId];
        if($status!==''){ $w.=' AND ar.status=?'; $p[]=$status; }
        return $this->query("SELECT ar.*, u.full_name AS requester_name FROM approval_requests ar
            JOIN users u ON u.id=ar.requester_id $w ORDER BY ar.status='pending' DESC, ar.id DESC", $p);
    }
    public function myRequests(int $userId): array
    {
        return $this->query("SELECT ar.*, a.full_name AS approver_name FROM approval_requests ar
            LEFT JOIN users a ON a.id=ar.approver_id WHERE ar.requester_id=? ORDER BY ar.id DESC", [$userId]);
    }
    public function requestFind(int $id): ?array { return $this->first("SELECT * FROM approval_requests WHERE id=?", [$id]); }
    public function requestCreate(array $d): int
    {
        $no='REQ-'.date('Y').'-'.str_pad((string)(($this->first("SELECT COUNT(*) c FROM approval_requests")['c']??0)+1),3,'0',STR_PAD_LEFT);
        $this->execute("INSERT INTO approval_requests (school_id, request_no, requester_id, request_type, title, detail, amount, approver_id, status)
            VALUES (1,?,?,?,?,?,?,?,'pending')",
            [$no,$d['requester_id'],$d['request_type'],$d['title'],$d['detail'],$d['amount']?:null,$d['approver_id']?:null]);
        return $this->lastId();
    }
    public function requestDecide(int $id, string $status, ?string $note): void
    { $this->execute("UPDATE approval_requests SET status=?, decision_note=?, decided_at=NOW() WHERE id=?", [$status,$note,$id]); }
    public function approversList(): array
    {
        return $this->query("SELECT u.id, u.full_name FROM users u JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id WHERE r.code IN ('director','deputy_director','head_academic','head_budget','head_hr','head_general','super_admin')
            AND u.deleted_at IS NULL GROUP BY u.id ORDER BY u.full_name");
    }
    public function pendingApprovalCount(int $userId): int
    { return (int)($this->first("SELECT COUNT(*) c FROM approval_requests WHERE approver_id=? AND status='pending'", [$userId])['c']??0); }

    // ---------- หนังสือราชการ ----------
    public function officialDocs(string $type='', string $q=''): array
    {
        $w=['1=1']; $p=[];
        if($type!==''){ $w[]='d.doc_type=?'; $p[]=$type; }
        if($q!==''){ $w[]='(d.title LIKE ? OR d.doc_number LIKE ?)'; $l="%$q%"; array_push($p,$l,$l); }
        $ws='WHERE '.implode(' AND ',$w);
        return $this->query("SELECT d.*,
            (SELECT COUNT(*) FROM document_views dv WHERE dv.document_id=d.id) AS view_count,
            (SELECT COUNT(*) FROM document_recipients dr WHERE dr.document_id=d.id) AS recipient_count
            FROM documents d $ws ORDER BY d.doc_date DESC, d.id DESC", $p);
    }
    public function docFind(int $id): ?array { return $this->first("SELECT * FROM documents WHERE id=?", [$id]); }
    public function docRecipients(int $id): array
    {
        return $this->query("SELECT dr.*, u.full_name FROM document_recipients dr
            LEFT JOIN users u ON u.id=dr.recipient_id WHERE dr.document_id=? ORDER BY dr.id", [$id]);
    }
    public function docViews(int $id): array
    {
        return $this->query("SELECT dv.*, u.full_name, od.name AS dept_name FROM document_views dv
            LEFT JOIN users u ON u.id=dv.user_id LEFT JOIN org_departments od ON od.id=dv.department_id
            WHERE dv.document_id=? ORDER BY dv.viewed_at DESC", [$id]);
    }
    public function logDocView(int $docId, ?int $userId, ?int $deptId): void
    { $this->execute("INSERT INTO document_views (document_id, user_id, department_id) VALUES (?,?,?)", [$docId,$userId,$deptId]); }

    // ---------- E-Office ----------
    public function templates(): array { return $this->query("SELECT * FROM document_templates ORDER BY doc_kind, id"); }
    public function templateFind(int $id): ?array { return $this->first("SELECT * FROM document_templates WHERE id=?", [$id]); }

    // ---------- ปฏิทิน ----------
    public function events(?int $year=null, ?int $month=null): array
    {
        if($year && $month){
            return $this->query("SELECT * FROM calendar_events WHERE YEAR(event_date)=? AND MONTH(event_date)=? ORDER BY event_date, start_time", [$year,$month]);
        }
        return $this->query("SELECT * FROM calendar_events ORDER BY event_date, start_time");
    }
    public function eventFind(int $id): ?array
    { return $this->first("SELECT * FROM calendar_events WHERE id=?", [$id]); }
    public function eventCreate(array $d, ?int $by): int
    {
        $this->execute("INSERT INTO calendar_events (school_id, title, event_type, event_date, end_date, start_time, end_time, location, meeting_url, description, created_by)
            VALUES (1,?,?,?,?,?,?,?,?,?,?)",
            [$d['title'],$d['event_type'],$d['event_date'],$d['end_date']?:null,$d['start_time']?:null,$d['end_time']?:null,
             $d['location']?:null,$d['meeting_url']?:null,$d['description']?:null,$by]);
        return $this->lastId();
    }
    public function eventUpdate(int $id, array $d): void
    {
        $this->execute("UPDATE calendar_events SET title=?, event_type=?, event_date=?, end_date=?, start_time=?, end_time=?, location=?, meeting_url=?, description=? WHERE id=?",
            [$d['title'],$d['event_type'],$d['event_date'],$d['end_date']?:null,$d['start_time']?:null,$d['end_time']?:null,
             $d['location']?:null,$d['meeting_url']?:null,$d['description']?:null,$id]);
    }
    public function eventDelete(int $id): void
    { $this->execute("DELETE FROM calendar_events WHERE id=?", [$id]); }
    public function upcomingEvents(int $limit=6): array
    { return $this->query("SELECT * FROM calendar_events WHERE event_date >= CURRENT_DATE ORDER BY event_date LIMIT $limit"); }

    // ---------- Dashboard ผู้บริหาร ----------
    public function school(): ?array { return $this->first("SELECT * FROM schools WHERE id=1"); }
    public function personnelDept(int $personnelId): ?int
    {
        $r=$this->first("SELECT department_id FROM personnel WHERE id=?", [$personnelId]);
        return $r && $r['department_id']!==null ? (int)$r['department_id'] : null;
    }
    public function execStats(): array
    {
        $one=fn($sql)=>(int)($this->first($sql)['c']??0);
        return [
            'students'   => $one("SELECT COUNT(*) c FROM students WHERE deleted_at IS NULL AND status='studying'"),
            'personnel'  => $one("SELECT COUNT(*) c FROM personnel WHERE deleted_at IS NULL AND status='active'"),
            'projects'   => $one("SELECT COUNT(*) c FROM projects WHERE status='ongoing'"),
            'pending_req'=> $one("SELECT COUNT(*) c FROM approval_requests WHERE status='pending'"),
            'budget_alloc'=> (float)($this->first("SELECT SUM(allocated_amount) c FROM budgets")['c']??0),
            'budget_used' => (float)($this->first("SELECT SUM(used_amount) c FROM budgets")['c']??0),
        ];
    }
}
