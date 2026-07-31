<?php
namespace App\Models;
use App\Core\Model;

/**
 * หัวข้อ PA (ข้อตกลงในการพัฒนางาน) รายบุคคล + ไฟล์ PDF ประกอบ
 * บุคลากรตั้งหัวข้อของตนเอง · ผู้ประเมินเรียกดูประกอบการประเมิน
 */
class PaTopic extends Model
{
    /** ด้าน/ประเภทของ PA (ว9/2564) */
    public const CATEGORIES = [
        'learning'  => 'ด้านที่ 1 การจัดการเรียนรู้',
        'support'   => 'ด้านที่ 2 การส่งเสริมและสนับสนุนการจัดการเรียนรู้',
        'self_dev'  => 'ด้านที่ 3 การพัฒนาตนเองและวิชาชีพ',
        'challenge' => 'ประเด็นท้าทายในการพัฒนาผลลัพธ์ผู้เรียน',
        'other'     => 'อื่นๆ',
    ];
    public static function categoryName(string $c): string
    { return self::CATEGORIES[$c] ?? $c; }

    /** personnel_id ของ user (จาก linked personnel) — 0 ถ้าไม่ผูก */
    public function personnelIdOfUser(?array $user): int
    {
        return ($user && ($user['linked_type'] ?? '')==='personnel') ? (int)($user['linked_id'] ?? 0) : 0;
    }
    public function personName(int $personnelId): string
    {
        $r=$this->first("SELECT CONCAT(prefix,first_name,' ',last_name) AS name, position
            FROM personnel WHERE id=?", [$personnelId]);
        return $r ? (string)$r['name'] : '';
    }

    // ---------- หัวข้อ ----------
    /** หัวข้อของบุคลากรคนหนึ่ง (พร้อมจำนวนไฟล์) */
    public function byPersonnel(int $personnelId, array $f=[]): array
    {
        $w='t.personnel_id=?'; $p=[$personnelId];
        if(!empty($f['year'])){ $w.=' AND t.year_be=?'; $p[]=(int)$f['year']; }
        return $this->query("SELECT t.*,
                (SELECT COUNT(*) FROM pa_topic_files f WHERE f.topic_id=t.id) AS file_count
            FROM pa_topics t WHERE $w
            ORDER BY t.year_be DESC, t.round, t.category, t.id", $p);
    }
    /** สำหรับผู้ประเมิน: หัวข้อทุกคน (กรองปี/บุคคล) พร้อมชื่อเจ้าของ */
    public function listAll(array $f=[]): array
    {
        $w=[]; $p=[];
        if(!empty($f['year'])){ $w[]='t.year_be=?'; $p[]=(int)$f['year']; }
        if(!empty($f['person'])){ $w[]='t.personnel_id=?'; $p[]=(int)$f['person']; }
        $where=$w?('WHERE '.implode(' AND ',$w)):'';
        return $this->query("SELECT t.*,
                CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS person_name,
                (SELECT COUNT(*) FROM pa_topic_files f WHERE f.topic_id=t.id) AS file_count
            FROM pa_topics t JOIN personnel pe ON pe.id=t.personnel_id
            $where ORDER BY pe.first_name, t.year_be DESC, t.round, t.category", $p);
    }
    public function find(int $id): ?array
    { return $this->first("SELECT * FROM pa_topics WHERE id=?", [$id]); }

    public function create(int $personnelId, array $d, ?int $by): int
    {
        $this->execute("INSERT INTO pa_topics (personnel_id, year_be, round, category, title, description, created_by)
            VALUES (?,?,?,?,?,?,?)",
            [$personnelId, (int)$d['year_be'], max(1,min(2,(int)$d['round'])),
             $this->cat($d['category']), trim((string)$d['title']),
             trim((string)$d['description'])?:null, $by]);
        return $this->lastId();
    }
    public function update(int $id, array $d): void
    {
        $this->execute("UPDATE pa_topics SET year_be=?, round=?, category=?, title=?, description=? WHERE id=?",
            [(int)$d['year_be'], max(1,min(2,(int)$d['round'])), $this->cat($d['category']),
             trim((string)$d['title']), trim((string)$d['description'])?:null, $id]);
    }
    public function delete(int $id): void
    { $this->execute("DELETE FROM pa_topics WHERE id=?", [$id]); }

    private function cat(string $c): string
    { return isset(self::CATEGORIES[$c]) ? $c : 'other'; }

    // ---------- ไฟล์ ----------
    public function files(int $topicId): array
    { return $this->query("SELECT * FROM pa_topic_files WHERE topic_id=? ORDER BY id", [$topicId]); }
    public function filesForTopics(array $topicIds): array
    {
        if(!$topicIds) return [];
        $in=implode(',', array_fill(0,count($topicIds),'?'));
        $rows=$this->query("SELECT * FROM pa_topic_files WHERE topic_id IN ($in) ORDER BY id", array_map('intval',$topicIds));
        $m=[]; foreach($rows as $r) $m[(int)$r['topic_id']][]=$r; return $m;
    }
    public function fileAdd(int $topicId, string $path, string $orig, ?string $mime, int $size, ?int $by): int
    {
        $this->execute("INSERT INTO pa_topic_files (topic_id, file_path, original_name, mime_type, size_bytes, uploaded_by)
            VALUES (?,?,?,?,?,?)", [$topicId,$path,$orig,$mime,$size,$by]);
        return $this->lastId();
    }
    public function fileFind(int $id): ?array
    { return $this->first("SELECT * FROM pa_topic_files WHERE id=?", [$id]); }
    public function fileDelete(int $id): void
    { $this->execute("DELETE FROM pa_topic_files WHERE id=?", [$id]); }
    /** เจ้าของไฟล์ (personnel_id) เพื่อตรวจสิทธิ์ */
    public function fileOwner(int $fileId): ?int
    {
        $r=$this->first("SELECT t.personnel_id FROM pa_topic_files f
            JOIN pa_topics t ON t.id=f.topic_id WHERE f.id=?", [$fileId]);
        return $r ? (int)$r['personnel_id'] : null;
    }
}
