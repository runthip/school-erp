<?php
namespace App\Models;
use App\Core\Model;

class Setting extends Model
{
    protected string $table = 'system_settings';

    /** ค่าทั้งหมด จัดกลุ่ม: [group_key => [setting_key => row]] */
    public function all(): array
    {
        $rows=$this->query("SELECT * FROM system_settings ORDER BY group_key, setting_key");
        $out=[];
        foreach($rows as $r) $out[$r['group_key']][$r['setting_key']]=$r;
        return $out;
    }

    /** อ่านค่าเดียว (แปลงชนิดให้) */
    public function get(string $group, string $key, mixed $default=null): mixed
    {
        $r=$this->first("SELECT setting_value, value_type FROM system_settings WHERE group_key=? AND setting_key=?", [$group,$key]);
        if(!$r) return $default;
        return $this->cast($r['setting_value'], $r['value_type']);
    }

    public function set(string $group, string $key, mixed $value, ?int $by=null): void
    {
        if(is_bool($value)) $value=$value?'1':'0';
        $this->execute("INSERT INTO system_settings (group_key, setting_key, setting_value, updated_by)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=VALUES(updated_by)",
            [$group,$key,(string)$value,$by]);
    }

    private function cast(?string $v, string $type): mixed
    {
        return match($type){
            'int'  => (int)$v,
            'bool' => in_array($v,['1','true','on'],true),
            'json' => json_decode((string)$v, true) ?? [],
            default=> (string)$v,
        };
    }

    // ---------- ข้อมูลโรงเรียน (ป้อนหัวเอกสารทุกใบพิมพ์) ----------
    public function school(): array
    { return $this->first("SELECT * FROM schools ORDER BY id LIMIT 1") ?? []; }

    /** ตั้ง/ล้าง path โลโก้โรงเรียน */
    public function schoolLogoUpdate(?string $path): void
    { $this->execute("UPDATE schools SET logo_path=? WHERE id=(SELECT id FROM (SELECT id FROM schools ORDER BY id LIMIT 1) t)", [$path]); }

    public function schoolUpdate(array $d): void
    {
        $this->execute("UPDATE schools SET school_code=?, name_th=?, name_en=?, address=?, district=?,
            province=?, postcode=?, phone=?, email=?, affiliation=? WHERE id=?",
            [$d['school_code'],$d['name_th'],$d['name_en']?:null,$d['address']?:null,$d['district']?:null,
             $d['province']?:null,$d['postcode']?:null,$d['phone']?:null,$d['email']?:null,$d['affiliation']?:null,
             (int)($d['id'] ?? 1)]);
    }

    // ---------- ปีการศึกษา / ภาคเรียน ----------
    public function years(): array
    {
        return $this->query("SELECT ay.*, (SELECT COUNT(*) FROM semesters s WHERE s.academic_year_id=ay.id) AS terms
            FROM academic_years ay ORDER BY ay.year_be DESC");
    }
    public function semesters(): array
    {
        return $this->query("SELECT s.*, ay.year_be FROM semesters s
            JOIN academic_years ay ON ay.id=s.academic_year_id ORDER BY ay.year_be DESC, s.term");
    }
    public function yearAdd(int $yearBe): int
    {
        $this->execute("INSERT INTO academic_years (school_id, year_be, is_current) VALUES (1,?,0)", [$yearBe]);
        $id=$this->lastId();
        $this->execute("INSERT INTO semesters (academic_year_id, term, is_current) VALUES (?,1,0),(?,2,0)", [$id,$id]);
        return $id;
    }
    public function yearSetCurrent(int $id): void
    {
        $this->execute("UPDATE academic_years SET is_current=0");
        $this->execute("UPDATE academic_years SET is_current=1 WHERE id=?", [$id]);
    }
    public function semesterSetCurrent(int $id): void
    {
        $this->execute("UPDATE semesters SET is_current=0");
        $this->execute("UPDATE semesters SET is_current=1 WHERE id=?", [$id]);
    }
    public function yearDelete(int $id): bool
    {
        $cur=$this->first("SELECT is_current FROM academic_years WHERE id=?", [$id]);
        if(!$cur || (int)$cur['is_current']===1) return false;   // ปีปัจจุบันลบไม่ได้
        $used=$this->first("SELECT COUNT(*) c FROM semesters s
            JOIN teaching_assignments ta ON ta.semester_id=s.id WHERE s.academic_year_id=?", [$id]);
        if((int)($used['c']??0)>0) return false;                 // มีการใช้งานแล้ว
        $this->execute("DELETE FROM semesters WHERE academic_year_id=?", [$id]);
        $this->execute("DELETE FROM academic_years WHERE id=?", [$id]);
        return true;
    }

    /** สถิติระบบสำหรับหน้าตั้งค่า */
    public function stats(): array
    {
        $one=fn($sql)=>(int)($this->first($sql)['c']??0);
        return [
            'users'    =>$one("SELECT COUNT(*) c FROM users WHERE deleted_at IS NULL"),
            'roles'    =>$one("SELECT COUNT(*) c FROM roles"),
            'perms'    =>$one("SELECT COUNT(*) c FROM permissions"),
            'students' =>$one("SELECT COUNT(*) c FROM students WHERE deleted_at IS NULL"),
            'personnel'=>$one("SELECT COUNT(*) c FROM personnel WHERE deleted_at IS NULL"),
            'audit'    =>$one("SELECT COUNT(*) c FROM audit_logs"),
        ];
    }
}
