<?php
namespace App\Models;
use App\Core\Model;

/**
 * จัดสอนแทน — วันที่ครูลา ระบบหาคาบที่ต้องสอนแทน และแนะนำครูที่มีคาบสอนน้อยมาสอนแทน
 */
class Substitution extends Model
{
    protected string $table = 'substitute_teachings';

    public const DAYS = [1=>'จันทร์',2=>'อังคาร',3=>'พุธ',4=>'พฤหัสบดี',5=>'ศุกร์',6=>'เสาร์',7=>'อาทิตย์'];

    /** ครูที่ลา (อนุมัติแล้ว) ครอบคลุมวันที่ */
    public function teachersOnLeave(string $date): array
    {
        return $this->query("SELECT DISTINCT p.id, CONCAT(p.prefix,p.first_name,' ',p.last_name) AS name,
                lt.name AS leave_type, l.reason
            FROM leaves l
            JOIN personnel p ON p.id=l.personnel_id
            LEFT JOIN leave_types lt ON lt.id=l.leave_type_id
            WHERE l.status='approved' AND ? BETWEEN l.start_date AND l.end_date
            ORDER BY p.first_name", [$date]);
    }

    /** คาบที่ต้องจัดสอนแทนในวันนั้น (คาบของครูที่ลา) + ครูสอนแทนที่จัดไว้แล้ว */
    public function coverage(string $date): array
    {
        $dow=(int)date('N', strtotime($date));               // 1=จันทร์ .. 7=อาทิตย์
        return $this->query("SELECT cs.id AS schedule_id, cs.period_no, cs.start_time, cs.end_time,
                ta.teacher_id AS absent_id, CONCAT(ap.prefix,ap.first_name,' ',ap.last_name) AS absent_name,
                sub.name_th AS subject, c.name AS classroom,
                st.id AS sub_id, st.sub_teacher_id, CONCAT(sp.prefix,sp.first_name,' ',sp.last_name) AS sub_name, st.note, st.status
            FROM class_schedules cs
            JOIN teaching_assignments ta ON ta.id=cs.teaching_assignment_id
            JOIN personnel ap ON ap.id=ta.teacher_id
            LEFT JOIN subjects sub ON sub.id=ta.subject_id
            LEFT JOIN classrooms c ON c.id=ta.classroom_id
            LEFT JOIN substitute_teachings st ON st.class_schedule_id=cs.id AND st.sub_date=?
            LEFT JOIN personnel sp ON sp.id=st.sub_teacher_id
            WHERE cs.day_of_week=?
              AND ta.teacher_id IN (
                SELECT l.personnel_id FROM leaves l
                WHERE l.status='approved' AND ? BETWEEN l.start_date AND l.end_date)
            ORDER BY cs.period_no", [$date,$dow,$date]);
    }

    /** ครูที่ว่างในคาบนั้น เรียงตามภาระงานสอนน้อยไปมาก (แนะนำคนคาบน้อยก่อน) */
    public function candidates(string $date, int $dow, int $period, int $absentId): array
    {
        return $this->query("SELECT p.id, CONCAT(p.prefix,p.first_name,' ',p.last_name) AS name, p.position,
                COALESCE((SELECT SUM(ta.weekly_periods) FROM teaching_assignments ta WHERE ta.teacher_id=p.id),0) AS weekly_load
            FROM personnel p
            WHERE p.deleted_at IS NULL AND p.status='active' AND p.id<>?
              AND (p.position LIKE '%ครู%' OR EXISTS(SELECT 1 FROM teaching_assignments t WHERE t.teacher_id=p.id))
              AND NOT EXISTS (SELECT 1 FROM class_schedules cs JOIN teaching_assignments ta ON ta.id=cs.teaching_assignment_id
                              WHERE ta.teacher_id=p.id AND cs.day_of_week=? AND cs.period_no=?)
              AND NOT EXISTS (SELECT 1 FROM leaves l WHERE l.personnel_id=p.id AND l.status='approved'
                              AND ? BETWEEN l.start_date AND l.end_date)
              AND NOT EXISTS (SELECT 1 FROM teacher_unavailable tu WHERE tu.teacher_id=p.id AND tu.day_of_week=?
                              AND (tu.period_no=? OR tu.period_no IS NULL))
              AND NOT EXISTS (SELECT 1 FROM substitute_teachings s JOIN class_schedules cs2 ON cs2.id=s.class_schedule_id
                              WHERE s.sub_teacher_id=p.id AND s.sub_date=? AND cs2.period_no=? AND cs2.day_of_week=?)
            ORDER BY weekly_load ASC, p.first_name", [$absentId,$dow,$period,$date,$dow,$period,$date,$period,$dow]);
    }

    public function assign(int $scheduleId, string $date, int $absentId, int $subId, ?string $note, ?int $by): void
    {
        $this->execute("INSERT INTO substitute_teachings (sub_date, class_schedule_id, absent_teacher_id, sub_teacher_id, note, created_by)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE sub_teacher_id=VALUES(sub_teacher_id), absent_teacher_id=VALUES(absent_teacher_id), note=VALUES(note), created_by=VALUES(created_by)",
            [$date,$scheduleId,$absentId,$subId,$note?:null,$by]);
    }

    public function remove(int $scheduleId, string $date): void
    { $this->execute("DELETE FROM substitute_teachings WHERE class_schedule_id=? AND sub_date=?", [$scheduleId,$date]); }

    /**
     * จัดสอนแทนอัตโนมัติทุกคาบที่ยังว่าง — เลือกครูคาบสอนน้อยสุดที่ว่าง
     * กันไม่ให้ครูคนเดียวถูกจัดซ้ำคาบเดียวกัน · คืนจำนวนที่จัดได้
     */
    public function autoAssign(string $date, ?int $by): int
    {
        $dow=(int)date('N', strtotime($date));
        $n=0;
        foreach($this->coverage($date) as $row){
            if(!empty($row['sub_id'])) continue;             // จัดแล้ว ข้าม
            $cands=$this->candidates($date,$dow,(int)$row['period_no'],(int)$row['absent_id']);
            if(!$cands) continue;                             // ไม่มีครูว่าง
            $this->assign((int)$row['schedule_id'],$date,(int)$row['absent_id'],(int)$cands[0]['id'],'จัดอัตโนมัติ (คาบน้อยสุด)',$by);
            $n++;
        }
        return $n;
    }

    /**
     * อนุมัติการจัดสอนแทนทั้งวัน (โดยหัวหน้าฝ่าย/แอดมินวิชาการ)
     * แล้วแจ้งเตือนครูที่รับมอบหมายสอนแทน · คืน [approved, notified]
     */
    public function approve(string $date, ?int $by): array
    {
        // รายการที่ยังไม่อนุมัติ (เพื่อส่งแจ้งเตือนเฉพาะครั้งแรก)
        $pending=$this->query("SELECT st.id, st.sub_teacher_id, st.absent_teacher_id, cs.period_no,
                sub.name_th AS subject, c.name AS classroom
            FROM substitute_teachings st
            JOIN class_schedules cs ON cs.id=st.class_schedule_id
            JOIN teaching_assignments ta ON ta.id=cs.teaching_assignment_id
            LEFT JOIN subjects sub ON sub.id=ta.subject_id
            LEFT JOIN classrooms c ON c.id=ta.classroom_id
            WHERE st.sub_date=? AND st.status='assigned'", [$date]);
        if(!$pending) return ['approved'=>0,'notified'=>0];

        $this->execute("UPDATE substitute_teachings SET status='approved', approved_by=?, approved_at=NOW()
            WHERE sub_date=? AND status='assigned'", [$by,$date]);

        $notif=new Notification();
        $absentNames=[];
        $beDate=(int)date('j',strtotime($date)).'/'.(int)date('n',strtotime($date)).'/'.(date('Y',strtotime($date))+543);
        $notified=0;
        foreach($pending as $p){
            $body='วันที่ '.$beDate.' คาบ '.(int)$p['period_no'].' วิชา '.($p['subject']?:'-').' ห้อง '.($p['classroom']?:'-').' (แทนครูที่ลา)';
            if($notif->notifyPersonnel((int)$p['sub_teacher_id'], 'ได้รับมอบหมายสอนแทน', $body, 'substitute?date='.$date)) $notified++;
        }
        $this->execute("UPDATE substitute_teachings SET notified_at=NOW() WHERE sub_date=? AND status='approved' AND notified_at IS NULL", [$date]);
        return ['approved'=>count($pending),'notified'=>$notified];
    }

    /** สรุปการจัดสอนแทนของวัน (สำหรับพิมพ์/ภาพรวม) */
    public function listByDate(string $date): array
    {
        return $this->query("SELECT st.*, cs.period_no,
                CONCAT(ap.prefix,ap.first_name,' ',ap.last_name) AS absent_name,
                CONCAT(sp.prefix,sp.first_name,' ',sp.last_name) AS sub_name,
                sub.name_th AS subject, c.name AS classroom
            FROM substitute_teachings st
            JOIN class_schedules cs ON cs.id=st.class_schedule_id
            JOIN teaching_assignments ta ON ta.id=cs.teaching_assignment_id
            LEFT JOIN personnel ap ON ap.id=st.absent_teacher_id
            LEFT JOIN personnel sp ON sp.id=st.sub_teacher_id
            LEFT JOIN subjects sub ON sub.id=ta.subject_id
            LEFT JOIN classrooms c ON c.id=ta.classroom_id
            WHERE st.sub_date=? ORDER BY cs.period_no", [$date]);
    }
}
