<?php
namespace App\Models;
use App\Core\Model;

/**
 * สรุปยอดนักเรียน — นับตามชั้น/ห้อง แยกชาย/หญิง + จัดกลุ่ม อนุบาล/ประถม/มัธยม
 * และรายชื่อ (roster) สำหรับพิมพ์บัญชีลงชื่อ (สิทธิ์ student.headcount)
 */
class StudentHeadcount extends Model
{
    /** จัดหมวดตามช่วงชั้น stage → [ชื่อหมวด, ลำดับ] */
    public static function category(?string $stage): array
    {
        return match($stage){
            'kindergarten'    => ['อนุบาล', 0],
            'primary'         => ['ประถมศึกษา', 1],
            'lower_secondary' => ['มัธยมศึกษาตอนต้น', 2],
            'upper_secondary' => ['มัธยมศึกษาตอนปลาย', 3],
            default           => ['อื่น ๆ', 9],
        };
    }

    private function where(array $f): array
    {
        $w=[enabled_stages_sql()]; $p=[];  // แสดงเฉพาะช่วงชั้นที่โรงเรียนเปิดใช้งาน
        if(!empty($f['stage'])){ $w[]='gl.stage=?'; $p[]=$f['stage']; }
        if(!empty($f['level'])){ $w[]='gl.id=?'; $p[]=(int)$f['level']; }
        if(!empty($f['classroom'])){ $w[]='c.id=?'; $p[]=(int)$f['classroom']; }
        return [$w?('WHERE '.implode(' AND ',$w)):'', $p];
    }

    /** ยอดต่อห้อง (แสดงทุกห้องแม้ไม่มีนักเรียน) */
    public function byClassroom(array $f=[]): array
    {
        [$where,$p]=$this->where($f);
        return $this->query("SELECT gl.id AS level_id, gl.name AS level_name, gl.level_order, gl.stage,
                c.id AS classroom_id, c.name AS classroom,
                COALESCE(SUM(s.gender='male'),0) AS male,
                COALESCE(SUM(s.gender='female'),0) AS female,
                COUNT(s.id) AS total
            FROM classrooms c
            LEFT JOIN grade_levels gl ON gl.id=c.grade_level_id
            LEFT JOIN student_enrollments se ON se.classroom_id=c.id AND se.status='active'
            LEFT JOIN students s ON s.id=se.student_id AND s.deleted_at IS NULL
            $where
            GROUP BY c.id
            ORDER BY (gl.level_order IS NULL), gl.level_order, c.name", $p);
    }

    /** รายชื่อนักเรียน (สำหรับบัญชีลงชื่อ/หมายเหตุ) */
    public function roster(array $f=[]): array
    {
        [$where,$p]=$this->where($f);
        $where = $where ? $where.' AND se.status=\'active\'' : "WHERE se.status='active'";
        return $this->query("SELECT s.student_code, s.prefix, s.first_name, s.last_name, s.gender,
                se.roll_number, c.id AS classroom_id, c.name AS classroom,
                gl.name AS level_name, gl.level_order, gl.stage
            FROM student_enrollments se
            JOIN students s ON s.id=se.student_id AND s.deleted_at IS NULL
            JOIN classrooms c ON c.id=se.classroom_id
            LEFT JOIN grade_levels gl ON gl.id=c.grade_level_id
            $where
            ORDER BY (gl.level_order IS NULL), gl.level_order, c.name,
                     (se.roll_number IS NULL), se.roll_number, s.student_code", $p);
    }

    /** สรุปย่อสำหรับแดชบอร์ด: ยอดรวม + ยอดชาย/หญิง + ยอดรายระดับชั้น + รายหมวด */
    public function summary(): array
    {
        $levels=$this->query("SELECT gl.id, gl.name, gl.level_order, gl.stage,
                COALESCE(SUM(s.gender='male'),0) AS m,
                COALESCE(SUM(s.gender='female'),0) AS f,
                COUNT(s.id) AS t
            FROM grade_levels gl
            LEFT JOIN classrooms c ON c.grade_level_id=gl.id
            LEFT JOIN student_enrollments se ON se.classroom_id=c.id AND se.status='active'
            LEFT JOIN students s ON s.id=se.student_id AND s.deleted_at IS NULL
            WHERE ".enabled_stages_sql()."
            GROUP BY gl.id ORDER BY gl.level_order");
        $gM=$gF=$gT=0; $cats=[];
        foreach($levels as $lv){
            $gM+=(int)$lv['m']; $gF+=(int)$lv['f']; $gT+=(int)$lv['t'];
            [$cl,$co]=self::category($lv['stage']);
            if(!isset($cats[$co])) $cats[$co]=['label'=>$cl,'m'=>0,'f'=>0,'t'=>0];
            $cats[$co]['m']+=(int)$lv['m']; $cats[$co]['f']+=(int)$lv['f']; $cats[$co]['t']+=(int)$lv['t'];
        }
        ksort($cats);
        return ['grand'=>['m'=>$gM,'f'=>$gF,'t'=>$gT], 'levels'=>$levels, 'categories'=>array_values($cats)];
    }

    public function filterOptions(): array
    {
        $stages=[]; foreach(all_stages() as $k=>$v){ if(stage_enabled($k)) $stages[]=['stage'=>$k,'label'=>$v]; }
        return [
            'levels'  => $this->query("SELECT gl.id, gl.name, gl.level_order, gl.stage FROM grade_levels gl WHERE ".enabled_stages_sql()." ORDER BY gl.level_order"),
            'classes' => $this->query("SELECT c.id, c.name, c.grade_level_id FROM classrooms c LEFT JOIN grade_levels gl ON gl.id=c.grade_level_id WHERE ".enabled_stages_sql()." ORDER BY c.name"),
            'stages'  => $stages,
        ];
    }
}
