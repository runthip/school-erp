<?php
namespace App\Models;

use App\Core\Model;

/**
 * รายงานผลการปฏิบัติงานและผลการประเมินตนเอง (SAR)
 */
class Sar extends Model
{
    protected string $table = 'sar_reports';

    /** โครงสร้างการประเมินตามมาตรฐานตำแหน่ง + จรรยาบรรณ (ใช้สร้างฟอร์มเปล่า) */
    public const EVAL_TEMPLATE = [
        'teaching' => [
            'label' => 'ด้านการจัดการเรียนการสอน',
            'items' => [
                'จัดทำแผนการจัดการเรียนรู้ที่เน้นผู้เรียนเป็นสำคัญ',
                'ผลิต/ใช้สื่อ นวัตกรรม เทคโนโลยีในการจัดการเรียนรู้',
                'ออกแบบการวัดและประเมินผลตามสภาพจริง',
                'จัดกิจกรรมการเรียนรู้ที่ส่งเสริมทักษะการคิด',
            ],
        ],
        'classroom' => [
            'label' => 'ด้านการบริหารจัดการชั้นเรียน',
            'items' => [
                'จัดระบบดูแลช่วยเหลือนักเรียนอย่างทั่วถึง',
                'อบรมบ่มนิสัย ปลูกฝังคุณธรรมจริยธรรม',
                'จัดบรรยากาศและสภาพแวดล้อมที่เอื้อต่อการเรียนรู้',
            ],
        ],
        'development' => [
            'label' => 'ด้านการพัฒนาตนเองและวิชาชีพ',
            'items' => [
                'พัฒนาตนเองด้วยการอบรม/สัมมนา/ศึกษาดูงาน',
                'ร่วมกระบวนการชุมชนแห่งการเรียนรู้ (PLC)',
                'นำความรู้มาพัฒนาการจัดการเรียนการสอน',
            ],
        ],
        'conduct' => [
            'label' => 'การประเมินผลการปฏิบัติตน (จรรยาบรรณวิชาชีพ)',
            'items' => [
                'การรักษาวินัยและปฏิบัติตามระเบียบของทางราชการ',
                'การมีคุณธรรม จริยธรรม เป็นแบบอย่างที่ดี',
                'จรรยาบรรณต่อวิชาชีพ ผู้เรียน และผู้ร่วมงาน',
                'การครองตน ครองคน ครองงาน',
            ],
        ],
    ];

    public const STATUS = [
        'draft'     => ['ร่าง','bg-slate-100 text-slate-600'],
        'submitted' => ['ส่งแล้ว รอตรวจ','bg-amber-100 text-amber-700'],
        'reviewed'  => ['รอง ผอ.ให้ความเห็นแล้ว','bg-blue-100 text-blue-700'],
        'approved'  => ['ผอ.อนุมัติแล้ว','bg-emerald-100 text-emerald-700'],
        'returned'  => ['ส่งกลับแก้ไข','bg-red-100 text-red-700'],
    ];

    /* ---------- อ่าน ---------- */

    public function find(int $id): ?array
    {
        $r = $this->first("SELECT s.*,
                CONCAT(p.prefix,p.first_name,' ',p.last_name) AS teacher_name,
                p.position AS person_position, p.academic_standing AS person_standing
            FROM sar_reports s
            JOIN personnel p ON p.id = s.personnel_id
            WHERE s.id = ?", [$id]);
        if ($r) $this->decode($r);
        return $r;
    }

    /** SAR ของครูคนหนึ่งในปีหนึ่ง (ถ้ามี) */
    public function findByPersonYear(int $personnelId, int $year): ?array
    {
        $r = $this->first("SELECT * FROM sar_reports WHERE personnel_id=? AND academic_year=?",
            [$personnelId, $year]);
        if ($r) $this->decode($r);
        return $r;
    }

    /** รายการ SAR ทั้งหมด (กรองปี/สถานะได้) */
    public function listReports(array $f = []): array
    {
        $w = "1=1"; $p = [];
        if (!empty($f['year']))   { $w .= " AND s.academic_year=?"; $p[] = (int)$f['year']; }
        if (!empty($f['status'])) { $w .= " AND s.status=?";        $p[] = $f['status']; }
        if (!empty($f['personnel_id'])) { $w .= " AND s.personnel_id=?"; $p[] = (int)$f['personnel_id']; }
        return $this->query("SELECT s.id, s.academic_year, s.status, s.eval_score,
                s.submitted_at, s.approved_at, s.subject_group, s.position,
                CONCAT(p.prefix,p.first_name,' ',p.last_name) AS teacher_name,
                d.name AS department
            FROM sar_reports s
            JOIN personnel p ON p.id = s.personnel_id
            LEFT JOIN org_departments d ON d.id = p.department_id
            WHERE $w ORDER BY s.academic_year DESC, teacher_name", $p);
    }

    /** สถิติภาพรวมของปี */
    public function yearStats(int $year): array
    {
        $r = $this->first("SELECT
            COUNT(*) total,
            SUM(status='draft') draft,
            SUM(status='submitted') submitted,
            SUM(status='reviewed') reviewed,
            SUM(status='approved') approved,
            SUM(status='returned') returned,
            AVG(eval_score) avg_score
            FROM sar_reports WHERE academic_year=?", [$year]);
        return $r ?: [];
    }

    /** ปีการศึกษาที่มีข้อมูล + ปีปัจจุบัน */
    public function years(): array
    {
        $rows = $this->query("SELECT DISTINCT academic_year y FROM sar_reports ORDER BY y DESC");
        $ys = array_map(fn($r)=>(int)$r['y'], $rows);
        $cur = (int)date('Y') + 543;
        if (!in_array($cur, $ys, true)) array_unshift($ys, $cur);
        return $ys;
    }

    /* ---------- เขียน ---------- */

    /** สร้าง SAR เปล่าให้ครู (ถ้ายังไม่มีในปีนั้น) แล้วคืน id */
    public function ensure(int $personnelId, int $year, ?int $by): int
    {
        $ex = $this->findByPersonYear($personnelId, $year);
        if ($ex) return (int)$ex['id'];

        // ดึงข้อมูลครูมาเติมค่าเริ่มต้น
        $p = $this->first("SELECT p.*, sg.name AS sg_name FROM personnel p
             LEFT JOIN subject_groups sg ON sg.id = p.subject_group_id WHERE p.id=?", [$personnelId]);
        $this->execute("INSERT INTO sar_reports
            (school_id, personnel_id, academic_year, position, academic_standing, subject_group, created_by)
            VALUES (1, ?, ?, ?, ?, ?, ?)",
            [$personnelId, $year, $p['position'] ?? null, $p['academic_standing'] ?? null,
             $p['sg_name'] ?? null, $by]);
        return $this->lastId();
    }

    /** บันทึกส่วนหัว + ตอนต่างๆ (รับเฉพาะ key ที่อนุญาต) */
    public function saveSection(int $id, string $section, mixed $value): void
    {
        $jsonCols = ['self_data','develop_data','duties_data','results_data','student_data','improve_data','eval_data'];
        $textCols = ['position','academic_standing','subject_group','special_duties'];
        if (in_array($section, $jsonCols, true)) {
            $this->execute("UPDATE sar_reports SET $section=? WHERE id=?",
                [json_encode($value, JSON_UNESCAPED_UNICODE), $id]);
            if ($section === 'eval_data') $this->recomputeScore($id, $value);
        } elseif (in_array($section, $textCols, true)) {
            $this->execute("UPDATE sar_reports SET $section=? WHERE id=?", [$value, $id]);
        } elseif ($section === 'teach_hours') {
            $this->execute("UPDATE sar_reports SET teach_hours=? WHERE id=?", [$value !== '' ? (float)$value : null, $id]);
        }
    }

    /** คำนวณคะแนนเฉลี่ยจาก eval_data */
    private function recomputeScore(int $id, mixed $eval): void
    {
        $sum = 0; $n = 0;
        if (is_array($eval)) {
            foreach ($eval as $group) {
                foreach (($group['scores'] ?? []) as $sc) {
                    if ($sc !== '' && $sc !== null) { $sum += (float)$sc; $n++; }
                }
            }
        }
        $avg = $n > 0 ? round($sum / $n, 2) : null;
        $this->execute("UPDATE sar_reports SET eval_score=? WHERE id=?", [$avg, $id]);
    }

    /* ---------- workflow ---------- */

    public function submit(int $id): void
    {
        $this->execute("UPDATE sar_reports SET status='submitted', submitted_at=NOW() WHERE id=? AND status IN ('draft','returned')", [$id]);
    }
    public function review(int $id, int $reviewerId, string $comment): void
    {
        $this->execute("UPDATE sar_reports SET status='reviewed', reviewer_id=?, reviewer_comment=?, reviewed_at=NOW()
            WHERE id=? AND status='submitted'", [$reviewerId, $comment, $id]);
    }
    public function approve(int $id, int $directorId, string $comment): void
    {
        $this->execute("UPDATE sar_reports SET status='approved', director_id=?, director_comment=?, approved_at=NOW()
            WHERE id=? AND status IN ('reviewed','submitted')", [$directorId, $comment, $id]);
    }
    public function returnToTeacher(int $id, string $comment): void
    {
        $this->execute("UPDATE sar_reports SET status='returned', reviewer_comment=?, reviewed_at=NOW()
            WHERE id=? AND status IN ('submitted','reviewed')", [$comment, $id]);
    }

    /* ---------- ไฟล์แนบ ---------- */

    public function attachments(int $sarId, ?string $category = null): array
    {
        if ($category)
            return $this->query("SELECT * FROM sar_attachments WHERE sar_id=? AND category=? ORDER BY id", [$sarId, $category]);
        return $this->query("SELECT * FROM sar_attachments WHERE sar_id=? ORDER BY category, id", [$sarId]);
    }
    public function attachmentAdd(int $sarId, string $category, string $path, string $orig, string $mime, int $size, ?string $note, ?int $by): int
    {
        $this->execute("INSERT INTO sar_attachments (sar_id, category, file_path, original_name, mime_type, size_bytes, note, uploaded_by)
            VALUES (?,?,?,?,?,?,?,?)", [$sarId, $category, $path, $orig, $mime, $size, $note, $by]);
        return $this->lastId();
    }
    public function attachmentFind(int $id): ?array
    {
        return $this->first("SELECT * FROM sar_attachments WHERE id=?", [$id]);
    }
    public function attachmentDelete(int $id): void
    {
        $this->execute("DELETE FROM sar_attachments WHERE id=?", [$id]);
    }

    /* ---------- helper ---------- */

    /** แปลง JSON column เป็น array ในแถว */
    private function decode(array &$r): void
    {
        foreach (['self_data','develop_data','duties_data','results_data','student_data','improve_data','eval_data'] as $c) {
            $r[$c] = !empty($r[$c]) ? (json_decode($r[$c], true) ?: []) : [];
        }
    }
}
