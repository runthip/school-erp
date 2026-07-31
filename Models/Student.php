<?php
namespace App\Models;
use App\Core\Model;

class Student extends Model
{
    protected string $table = 'students';

    /** เงื่อนไข WHERE ร่วม (ค้นหา/สถานะ/เพศ/ชั้น/ห้อง) — ใช้ FROM ที่ JOIN ห้องเรียน */
    private function buildFilter(string $q, string $status, string $gender, int $grade, int $classroom): array
    {
        $w=['s.deleted_at IS NULL']; $p=[];
        if($q!==''){ $w[]='(s.student_code LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.nickname LIKE ?)'; $l="%$q%"; array_push($p,$l,$l,$l,$l); }
        if($status!==''){ $w[]='s.status=?'; $p[]=$status; }
        if($gender!==''){ $w[]='s.gender=?'; $p[]=$gender; }
        if($classroom>0){ $w[]='se.classroom_id=?'; $p[]=$classroom; }
        elseif($grade>0){ $w[]='c.grade_level_id=?'; $p[]=$grade; }
        return ['WHERE '.implode(' AND ',$w), $p];
    }

    private function fromJoin(): string
    {
        return "FROM students s
           LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.academic_year_id=(SELECT id FROM academic_years WHERE is_current=1 LIMIT 1)
           LEFT JOIN classrooms c ON c.id=se.classroom_id";
    }

    public function paginate(string $q='', string $status='', int $limit=20, int $offset=0, string $gender='', int $grade=0, int $classroom=0): array
    {
        [$ws,$p]=$this->buildFilter($q,$status,$gender,$grade,$classroom);
        return $this->query(
          "SELECT s.*, c.name AS classroom, se.roll_number ".$this->fromJoin()."
           $ws ORDER BY c.grade_level_id, c.section, se.roll_number, s.student_code LIMIT $limit OFFSET $offset", $p);
    }
    public function countAll(string $q='', string $status='', string $gender='', int $grade=0, int $classroom=0): int
    {
        [$ws,$p]=$this->buildFilter($q,$status,$gender,$grade,$classroom);
        return (int)($this->first("SELECT COUNT(*) c ".$this->fromJoin()." $ws",$p)['c']??0);
    }

    public function classroomsForFilter(): array
    { return $this->query("SELECT c.id, c.name FROM classrooms c ORDER BY c.grade_level_id, c.section"); }
    public function gradesForFilter(): array
    { return $this->query("SELECT id, name FROM grade_levels ORDER BY level_order, id"); }
    public function detail(int $id): ?array
    {
        return $this->first(
          "SELECT s.*, c.name AS classroom, se.roll_number
           FROM students s
           LEFT JOIN student_enrollments se ON se.student_id=s.id
           LEFT JOIN classrooms c ON c.id=se.classroom_id
           WHERE s.id=? LIMIT 1",[$id]);
    }
    public function guardians(int $id): array
    {
        return $this->query(
          "SELECT g.*, sg.relationship, sg.is_primary
           FROM student_guardians sg JOIN guardians g ON g.id=sg.guardian_id
           WHERE sg.student_id=?",[$id]);
    }
    public function behavior(int $id): array
    { return $this->query("SELECT * FROM behavior_records WHERE student_id=? ORDER BY record_date DESC LIMIT 10",[$id]); }
    public function health(int $id): array
    { return $this->query("SELECT * FROM health_records WHERE student_id=? ORDER BY record_date DESC LIMIT 5",[$id]); }
    public function statusCounts(): array
    {
        $rows=$this->query("SELECT status, COUNT(*) c FROM students WHERE deleted_at IS NULL GROUP BY status");
        $out=[]; foreach($rows as $r) $out[$r['status']]=(int)$r['c']; return $out;
    }

    /** ทุกคนตามตัวกรอง (สำหรับส่งออก CSV) */
    public function exportRows(string $q='', string $status='', string $gender='', int $grade=0, int $classroom=0): array
    {
        [$ws,$p]=$this->buildFilter($q,$status,$gender,$grade,$classroom);
        return $this->query(
          "SELECT s.citizen_id, s.student_code, s.prefix, s.first_name, s.last_name, s.gender, s.birth_date,
                  s.nationality, s.religion, s.status, c.name AS classroom, se.roll_number ".$this->fromJoin()."
           $ws ORDER BY c.grade_level_id, c.section, se.roll_number, s.student_code", $p);
    }

    // ---------- นำเข้าหลายคน (CSV รูปแบบ DMC) ----------
    public function codeExists(string $code): bool
    { return $code!=='' && (bool)$this->first("SELECT id FROM students WHERE student_code=? AND deleted_at IS NULL", [$code]); }
    public function citizenExists(string $cid): bool
    { return $cid!=='' && (bool)$this->first("SELECT id FROM students WHERE citizen_id=? AND deleted_at IS NULL", [$cid]); }

    public function classroomByName(string $name): ?array
    { return $this->first("SELECT id, academic_year_id FROM classrooms WHERE name=? LIMIT 1", [$name]); }

    public function insertStudent(array $d, ?int $by): int
    {
        $this->execute("INSERT INTO students
            (school_id, student_code, citizen_id, prefix, first_name, last_name, gender, birth_date, nationality, religion, status, admission_date, created_by)
            VALUES ((SELECT id FROM schools ORDER BY id LIMIT 1),?,?,?,?,?,?,?,?,?,'studying',CURDATE(),?)",
            [$d['student_code'],$d['citizen_id']?:null,$d['prefix']?:null,$d['first_name'],$d['last_name'],
             $d['gender']?:null,$d['birth_date']?:null,$d['nationality']?:null,$d['religion']?:null,$by]);
        return $this->lastId();
    }
    public function enroll(int $studentId, int $classroomId, int $ayId, ?int $roll): void
    {
        $this->execute("INSERT IGNORE INTO student_enrollments (student_id, classroom_id, academic_year_id, roll_number, status)
            VALUES (?,?,?,?,'active')", [$studentId,$classroomId,$ayId,$roll?:null]);
    }
}
