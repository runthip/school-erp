<?php
namespace App\Models;
use App\Core\Model;

/**
 * งานสุขภาพนักเรียน: บันทึกน้ำหนัก/ส่วนสูง/BMI รายบุคคล จัดเป็นรายห้องเรียน
 */
class Health extends Model
{
    protected string $table = 'health_records';

    /** เกณฑ์ BMI (มาตรฐานสากลอย่างง่าย) → [ข้อความ, สี] */
    public static function bmiStatus(?float $bmi): array
    {
        if($bmi===null || $bmi<=0) return ['-','slate'];
        if($bmi < 18.5) return ['น้ำหนักน้อย','amber'];
        if($bmi < 23.0) return ['สมส่วน','emerald'];
        if($bmi < 25.0) return ['ท้วม','yellow'];
        if($bmi < 30.0) return ['อ้วน','orange'];
        return ['อ้วนมาก','red'];
    }

    public static function calcBmi(?float $weight, ?float $height): ?float
    {
        if(!$weight || !$height || $height<=0) return null;
        $m=$height/100;
        return round($weight/($m*$m), 2);
    }

    /** ห้องเรียนพร้อมจำนวนนักเรียน + จำนวนที่บันทึกสุขภาพแล้วในวันที่เลือก */
    public function classrooms(string $date): array
    {
        return $this->query("SELECT c.id, c.name, gl.name AS grade,
            CONCAT(pe.first_name,' ',pe.last_name) AS homeroom_teacher,
            (SELECT COUNT(*) FROM student_enrollments se WHERE se.classroom_id=c.id AND se.status='active') AS student_count,
            (SELECT COUNT(DISTINCT h.student_id) FROM health_records h
                JOIN student_enrollments se2 ON se2.student_id=h.student_id AND se2.status='active' AND se2.classroom_id=c.id
                WHERE h.record_date=?) AS recorded
            FROM classrooms c
            JOIN grade_levels gl ON gl.id=c.grade_level_id
            LEFT JOIN personnel pe ON pe.id=c.homeroom_teacher_id
            ORDER BY gl.level_order, c.section", [$date]);
    }

    public function classroomFind(int $id): ?array
    {
        return $this->first("SELECT c.id, c.name, gl.name AS grade,
            CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS homeroom_teacher
            FROM classrooms c JOIN grade_levels gl ON gl.id=c.grade_level_id
            LEFT JOIN personnel pe ON pe.id=c.homeroom_teacher_id WHERE c.id=?", [$id]);
    }

    /** รายชื่อนักเรียนในห้อง + ค่าสุขภาพวันที่เลือก + ค่าครั้งก่อน (อ้างอิงการเจริญเติบโต) */
    public function roster(int $classroomId, string $date): array
    {
        return $this->query("SELECT s.id, s.student_code, s.gender, se.roll_number,
            CONCAT(s.prefix,s.first_name,' ',s.last_name) AS name,
            h.height_cm, h.weight_kg, h.bmi,
            (SELECT h2.weight_kg FROM health_records h2 WHERE h2.student_id=s.id AND h2.record_date < ? ORDER BY h2.record_date DESC LIMIT 1) AS prev_weight,
            (SELECT h2.height_cm FROM health_records h2 WHERE h2.student_id=s.id AND h2.record_date < ? ORDER BY h2.record_date DESC LIMIT 1) AS prev_height
            FROM students s
            JOIN student_enrollments se ON se.student_id=s.id AND se.status='active' AND se.classroom_id=?
            LEFT JOIN health_records h ON h.student_id=s.id AND h.record_date=?
            WHERE s.deleted_at IS NULL
            ORDER BY se.roll_number, s.student_code", [$date,$date,$classroomId,$date]);
    }

    /** บันทึก/แก้ไข ค่าสุขภาพของนักเรียนรายวัน (คำนวณ BMI ให้อัตโนมัติ) */
    public function save(int $studentId, string $date, ?float $height, ?float $weight): void
    {
        $bmi=self::calcBmi($weight,$height);
        $this->execute("DELETE FROM health_records WHERE student_id=? AND record_date=?", [$studentId,$date]);
        $this->execute("INSERT INTO health_records (student_id, record_date, height_cm, weight_kg, bmi)
            VALUES (?,?,?,?,?)", [$studentId,$date,$height,$weight,$bmi]);
    }

    /** ประวัติสุขภาพรายบุคคล (ล่าสุดก่อน) */
    public function studentHistory(int $studentId, int $limit=12): array
    {
        return $this->query("SELECT record_date, height_cm, weight_kg, bmi
            FROM health_records WHERE student_id=? ORDER BY record_date DESC LIMIT $limit", [$studentId]);
    }

    /** สรุป BMI ของห้องในวันที่เลือก */
    public function summary(int $classroomId, string $date): array
    {
        $rows=$this->query("SELECT h.bmi FROM health_records h
            JOIN student_enrollments se ON se.student_id=h.student_id AND se.status='active' AND se.classroom_id=?
            WHERE h.record_date=? AND h.bmi IS NOT NULL", [$classroomId,$date]);
        $s=['low'=>0,'normal'=>0,'over'=>0,'total'=>count($rows)];
        foreach($rows as $r){
            $b=(float)$r['bmi'];
            if($b<18.5) $s['low']++;
            elseif($b<23.0) $s['normal']++;
            else $s['over']++;
        }
        return $s;
    }
}
