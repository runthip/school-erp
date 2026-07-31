<?php
namespace App\Models;
use App\Core\Model;

/**
 * รายงานผลการดำเนินงานกิจกรรม/การแข่งขันของสถานศึกษา
 * กิจกรรม + ผู้เข้าแข่งขัน (หลายคน) + ไฟล์รูป/เกียรติบัตร · ใช้ประกอบ SAR/รายงานประจำปี
 */
class ActivityReport extends Model
{
    public const CATEGORIES = [
        'academic' => 'การแข่งขันทางวิชาการ',
        'sports'   => 'กีฬา/กรีฑา',
        'arts'     => 'ศิลปะ/ดนตรี/นาฏศิลป์',
        'scout'    => 'ลูกเสือ/เนตรนารี/บำเพ็ญประโยชน์',
        'other'    => 'กิจกรรมอื่น ๆ',
    ];
    public static function categoryName(string $c): string
    { return self::CATEGORIES[$c] ?? $c; }
    private function cat(string $c): string
    { return isset(self::CATEGORIES[$c]) ? $c : 'other'; }

    /** วัตถุประสงค์ท้ายบันทึกข้อความ */
    public const MEMO_PURPOSES = [
        'acknowledge' => 'จึงเรียนมาเพื่อโปรดทราบ',
        'consider'    => 'จึงเรียนมาเพื่อโปรดพิจารณา',
        'publish'     => 'จึงเรียนมาเพื่อโปรดทราบและเผยแพร่ประชาสัมพันธ์',
    ];

    /** บันทึกหัวบันทึกข้อความ (เสนอ ผอ.) */
    public function memoSave(int $id, array $d): void
    {
        $purpose = isset(self::MEMO_PURPOSES[$d['memo_purpose']]) ? $d['memo_purpose'] : 'acknowledge';
        $this->execute("UPDATE activity_reports SET memo_no=?, memo_date=?, memo_agency=?, memo_to=?,
                memo_purpose=?, reporter_name=?, reporter_position=? WHERE id=?",
            [trim((string)$d['memo_no'])?:null, $d['memo_date']?:null, trim((string)$d['memo_agency'])?:null,
             trim((string)$d['memo_to'])?:null, $purpose, trim((string)$d['reporter_name'])?:null,
             trim((string)$d['reporter_position'])?:null, $id]);
    }

    // ---------- รายการ + สถิติ ----------
    public function list(array $f = []): array
    {
        $w = []; $p = [];
        if (!empty($f['q']))    { $w[] = '(a.title LIKE ? OR a.organizer LIKE ? OR a.location LIKE ?)'; $like = '%'.$f['q'].'%'; array_push($p, $like, $like, $like); }
        if (!empty($f['cat']))  { $w[] = 'a.category = ?'; $p[] = $f['cat']; }
        if (!empty($f['year'])) { $w[] = 'a.year_be = ?'; $p[] = (int)$f['year']; }
        $where = $w ? ('WHERE '.implode(' AND ', $w)) : '';
        return $this->query("SELECT a.*, u.full_name AS creator,
                (SELECT COUNT(*) FROM activity_participants pa WHERE pa.report_id=a.id) AS participant_count,
                (SELECT COUNT(*) FROM activity_files af WHERE af.report_id=a.id) AS file_count
            FROM activity_reports a
            LEFT JOIN users u ON u.id=a.created_by
            $where ORDER BY a.date_start DESC, a.id DESC", $p);
    }
    public function stats(): array
    {
        $r = $this->first("SELECT COUNT(*) total,
                COALESCE(SUM(category='academic'),0) academic,
                COALESCE(SUM(category='sports'),0) sports,
                (SELECT COUNT(*) FROM activity_participants) parts
            FROM activity_reports") ?: [];
        return ['total'=>(int)($r['total']??0),'academic'=>(int)($r['academic']??0),
                'sports'=>(int)($r['sports']??0),'parts'=>(int)($r['parts']??0)];
    }
    public function years(): array
    {
        return array_column($this->query("SELECT DISTINCT year_be FROM activity_reports
            WHERE year_be IS NOT NULL ORDER BY year_be DESC"), 'year_be');
    }

    public function find(int $id): ?array
    {
        return $this->first("SELECT a.*, u.full_name AS creator
            FROM activity_reports a LEFT JOIN users u ON u.id=a.created_by WHERE a.id=?", [$id]);
    }

    public function create(array $d, ?int $by): int
    {
        $this->execute("INSERT INTO activity_reports
            (title, category, date_start, date_end, location, organizer, coaches, result_summary, summary, problems, suggestions, year_be, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [trim($d['title']), $this->cat($d['category']), $d['date_start']?:null, $d['date_end']?:null,
             trim($d['location'])?:null, trim($d['organizer'])?:null, trim($d['coaches'])?:null,
             trim($d['result_summary'])?:null, trim($d['summary'])?:null,
             trim($d['problems'])?:null, trim($d['suggestions'])?:null, $d['year_be']?:null, $by]);
        return $this->lastId();
    }
    public function update(int $id, array $d): void
    {
        $this->execute("UPDATE activity_reports SET title=?, category=?, date_start=?, date_end=?,
                location=?, organizer=?, coaches=?, result_summary=?, summary=?, problems=?, suggestions=?, year_be=? WHERE id=?",
            [trim($d['title']), $this->cat($d['category']), $d['date_start']?:null, $d['date_end']?:null,
             trim($d['location'])?:null, trim($d['organizer'])?:null, trim($d['coaches'])?:null,
             trim($d['result_summary'])?:null, trim($d['summary'])?:null,
             trim($d['problems'])?:null, trim($d['suggestions'])?:null, $d['year_be']?:null, $id]);
    }
    public function delete(int $id): void
    { $this->execute("DELETE FROM activity_reports WHERE id=?", [$id]); }

    // ---------- ผู้เข้าแข่งขัน ----------
    public function participants(int $reportId): array
    { return $this->query("SELECT * FROM activity_participants WHERE report_id=? ORDER BY sort_order, id", [$reportId]); }

    public function participantAdd(int $reportId, array $d, int $sort = 0): int
    {
        $this->execute("INSERT INTO activity_participants (report_id, name, grade_level, event_type, award, sort_order)
            VALUES (?,?,?,?,?,?)",
            [$reportId, trim($d['name']), trim($d['grade_level'])?:null,
             trim($d['event_type'])?:null, trim($d['award'])?:null, $sort]);
        return $this->lastId();
    }
    /** บันทึกผู้เข้าแข่งขันหลายคนพร้อมกัน (ข้ามแถวที่ไม่มีชื่อ) */
    public function participantsAddMany(int $reportId, array $names, array $grades, array $types, array $awards): int
    {
        $n = 0;
        foreach ($names as $i => $nm) {
            $nm = trim((string)$nm);
            if ($nm === '') continue;
            $this->participantAdd($reportId, [
                'name'=>$nm, 'grade_level'=>(string)($grades[$i]??''),
                'event_type'=>(string)($types[$i]??''), 'award'=>(string)($awards[$i]??''),
            ], $n);
            $n++;
        }
        return $n;
    }
    public function participantFind(int $id): ?array
    { return $this->first("SELECT * FROM activity_participants WHERE id=?", [$id]); }
    public function participantDelete(int $id): void
    { $this->execute("DELETE FROM activity_participants WHERE id=?", [$id]); }

    // ---------- ไฟล์ (รูป/เกียรติบัตร) ----------
    public function files(int $reportId): array
    { return $this->query("SELECT * FROM activity_files WHERE report_id=? ORDER BY kind, id", [$reportId]); }
    public function fileAdd(int $reportId, string $kind, string $path, string $orig, ?string $mime, int $size, string $caption, ?int $by): int
    {
        $kind = in_array($kind, ['photo','certificate'], true) ? $kind : 'photo';
        $this->execute("INSERT INTO activity_files (report_id, kind, file_path, original_name, mime_type, size_bytes, caption, uploaded_by)
            VALUES (?,?,?,?,?,?,?,?)",
            [$reportId, $kind, $path, $orig, $mime, $size, trim($caption)?:null, $by]);
        return $this->lastId();
    }
    public function fileFind(int $id): ?array
    { return $this->first("SELECT * FROM activity_files WHERE id=?", [$id]); }
    public function fileDelete(int $id): void
    { $this->execute("DELETE FROM activity_files WHERE id=?", [$id]); }

    /** ชื่อระดับชั้น (สำหรับ datalist ระดับชั้นผู้เข้าแข่งขัน) */
    public function gradeLevels(): array
    { return array_column($this->query("SELECT name FROM grade_levels ORDER BY level_order"), 'name'); }
}
