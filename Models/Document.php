<?php
namespace App\Models;
use App\Core\Model;

/**
 * งานสารบรรณ: ลงรับ (ตราปั๊ม) · ไฟล์แนบหลายไฟล์ · เกษียนหนังสือ (มอบหมาย/สั่งการ)
 */
class Document extends Model
{
    protected string $table = 'documents';

    public function list(array $f=[]): array
    {
        $w=['1=1']; $p=[];
        if(!empty($f['q'])){ $w[]='(d.title LIKE ? OR d.doc_number LIKE ? OR d.receive_no LIKE ? OR d.from_org LIKE ?)';
            $l='%'.$f['q'].'%'; array_push($p,$l,$l,$l,$l); }
        if(!empty($f['type'])){    $w[]='d.doc_type=?';        $p[]=$f['type']; }
        if(!empty($f['status'])){  $w[]='d.status=?';           $p[]=$f['status']; }
        if(!empty($f['urgency'])){ $w[]='d.urgency=?';          $p[]=$f['urgency']; }
        if(!empty($f['director'])){$w[]='d.director_status=?';  $p[]=$f['director']; }
        return $this->query("SELECT d.*,
            (SELECT COUNT(*) FROM document_attachments a WHERE a.document_id=d.id) AS file_count,
            (SELECT COUNT(*) FROM document_notes n WHERE n.document_id=d.id) AS note_count,
            (SELECT COUNT(*) FROM document_recipients r WHERE r.document_id=d.id) AS recip_total,
            (SELECT COUNT(*) FROM document_recipients r WHERE r.document_id=d.id AND r.status='done') AS recip_done,
            CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS assignee
            FROM documents d
            LEFT JOIN personnel pe ON pe.id=d.assigned_to
            WHERE ".implode(' AND ',$w)."
            ORDER BY d.received_at DESC, d.doc_date DESC, d.id DESC LIMIT 300", $p);
    }

    public function find(int $id): ?array
    {
        return $this->first("SELECT d.*,
            CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS assignee, pe.position AS assignee_position,
            dep.name AS assigned_dept_name,
            ru.full_name AS receiver_name,
            su.full_name AS signer_name
            FROM documents d
            LEFT JOIN personnel pe ON pe.id=d.assigned_to
            LEFT JOIN org_departments dep ON dep.id=d.assigned_dept
            LEFT JOIN users ru ON ru.id=d.received_by
            LEFT JOIN users su ON su.id=d.signed_by
            WHERE d.id=?", [$id]);
    }

    public function stats(): array
    {
        $one=fn($s)=>(int)($this->first($s)['c'] ?? 0);
        return [
            'incoming'   =>$one("SELECT COUNT(*) c FROM documents WHERE doc_type='incoming'"),
            'todayRecv'  =>$one("SELECT COUNT(*) c FROM documents WHERE DATE(received_at)=CURDATE()"),
            'waitDirector'=>$one("SELECT COUNT(*) c FROM documents WHERE doc_type='incoming' AND director_status='pending' AND received_at IS NOT NULL"),
            'inProcess'  =>$one("SELECT COUNT(*) c FROM documents WHERE status='in_process'"),
            'circularPending'=>$one("SELECT COUNT(DISTINCT d.id) c FROM documents d
                JOIN document_recipients r ON r.document_id=d.id
                WHERE d.doc_type='circular' AND r.status='pending'"),
        ];
    }

    // ---------- ลงรับ ----------
    /** เลขทะเบียนรับถัดไป (รันตามปีงบประมาณ พ.ศ.) */
    public function nextReceiveNo(): string
    {
        $r=$this->first("SELECT COALESCE(MAX(CAST(receive_no AS UNSIGNED)),0)+1 n
            FROM documents WHERE doc_type='incoming' AND receive_no REGEXP '^[0-9]+$'
              AND YEAR(received_at)=YEAR(CURDATE())");
        return str_pad((string)((int)($r['n'] ?? 1)), 4, '0', STR_PAD_LEFT);
    }

    /** เลขทะเบียนส่งถัดไป (คนละเล่มกับทะเบียนรับ) */
    public function nextSendNo(): string
    {
        $r=$this->first("SELECT COALESCE(MAX(CAST(send_no AS UNSIGNED)),0)+1 n
            FROM documents WHERE doc_type IN ('outgoing','circular') AND send_no REGEXP '^[0-9]+$'
              AND YEAR(COALESCE(sent_at,created_at))=YEAR(CURDATE())");
        return str_pad((string)((int)($r['n'] ?? 1)), 4, '0', STR_PAD_LEFT);
    }

    /** รหัสตรวจสอบเอกสาร (ใช้กับ QR) */
    public static function makeVerifyCode(int $id): string
    {
        return strtoupper('DOC'.date('y').'-'.str_pad((string)$id,5,'0',STR_PAD_LEFT).'-'.bin2hex(random_bytes(3)));
    }

    public function create(array $d, ?int $by=null): int
    {
        $schoolId=(int)($this->first("SELECT id FROM schools ORDER BY id LIMIT 1")['id'] ?? 1);
        $recvNo=null; $recvAt=null; $sendNo=null; $sentAt=null;
        if($d['doc_type']==='incoming'){
            $recvNo=$d['receive_no']!==''?$d['receive_no']:$this->nextReceiveNo();
            $recvAt=$d['received_at']!==''?$d['received_at']:date('Y-m-d H:i:s');
        } elseif(in_array($d['doc_type'],['outgoing','circular'],true)){
            $sendNo=$d['send_no']!==''?$d['send_no']:$this->nextSendNo();
            $sentAt=date('Y-m-d H:i:s');
        }
        $this->execute("INSERT INTO documents (school_id, doc_number, receive_no, send_no, doc_type, title,
            from_org, to_org, doc_date, received_date, received_at, received_by, sent_at,
            urgency, secret_level, status, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$schoolId, $d['doc_number']?:null, $recvNo, $sendNo, $d['doc_type'], $d['title'],
             $d['from_org']?:null, $d['to_org']?:null, $d['doc_date']?:null,
             $recvAt?date('Y-m-d',strtotime($recvAt)):null, $recvAt, $recvAt?$by:null, $sentAt,
             $d['urgency'], $d['secret_level'],
             $d['doc_type']==='incoming'?'in_process':'registered', $by]);
        $id=$this->lastId();
        // ทุกฉบับได้รหัสตรวจสอบทันที (ฟิลด์ qr_verify_code เดิมไม่เคยถูกใช้)
        $this->execute("UPDATE documents SET qr_verify_code=? WHERE id=?", [self::makeVerifyCode($id), $id]);
        return $id;
    }

    /** ตรวจสอบเอกสารจากรหัส (หน้าสาธารณะ — เปิดเผยเท่าที่จำเป็น) */
    public function verify(string $code): ?array
    {
        return $this->first("SELECT d.id, d.doc_number, d.receive_no, d.send_no, d.doc_type, d.title,
            d.from_org, d.to_org, d.doc_date, d.received_at, d.is_signed, d.signed_at,
            d.director_status, d.qr_verify_code,
            (SELECT n.author_name FROM document_notes n
              WHERE n.document_id=d.id AND n.kind='order' AND n.is_signed=1
              ORDER BY n.id DESC LIMIT 1) AS signer_name,
            (SELECT n.author_position FROM document_notes n
              WHERE n.document_id=d.id AND n.kind='order' AND n.is_signed=1
              ORDER BY n.id DESC LIMIT 1) AS signer_position
            FROM documents d WHERE d.qr_verify_code=? LIMIT 1", [$code]);
    }

    public function update(int $id, array $d): void
    {
        $this->execute("UPDATE documents SET doc_number=?, title=?, from_org=?, to_org=?, doc_date=?,
            urgency=?, secret_level=?, status=?, send_no=COALESCE(NULLIF(?,''),send_no) WHERE id=?",
            [$d['doc_number']?:null,$d['title'],$d['from_org']?:null,$d['to_org']?:null,$d['doc_date']?:null,
             $d['urgency'],$d['secret_level'],$d['status'],$d['send_no'] ?? '',$id]);
    }

    public function delete(int $id): void { $this->execute("DELETE FROM documents WHERE id=?", [$id]); }

    /** ประทับตรารับ (ถ้ายังไม่เคยลงรับ) */
    public function receive(int $id, ?int $by=null, string $receiveNo='', string $at=''): void
    {
        $no=$receiveNo!==''?$receiveNo:$this->nextReceiveNo();
        $when=$at!==''?$at:date('Y-m-d H:i:s');
        $this->execute("UPDATE documents SET receive_no=?, received_at=?, received_date=?, received_by=?,
            status=IF(status='draft','in_process',status) WHERE id=?",
            [$no,$when,date('Y-m-d',strtotime($when)),$by,$id]);
    }

    // ---------- ส่งต่อ / มอบหมาย (แท็กฝ่าย หรือเลือกครูรายคน) ----------
    public const ACTIONS = [
        'forward'     => 'มอบหมายให้ดำเนินการ',
        'acknowledge' => 'เพื่อทราบ',
        'approve'     => 'เพื่อพิจารณา/อนุมัติ',
        'comment'     => 'เพื่อให้ความเห็น',
        'sign'        => 'เพื่อลงนาม',
    ];

    public function recipients(int $docId): array
    {
        return $this->query("SELECT r.*, CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS name,
            pe.position, dep.name AS dept, fu.full_name AS forwarder
            FROM document_recipients r
            LEFT JOIN personnel pe ON pe.id=r.recipient_personnel_id
            LEFT JOIN org_departments dep ON dep.id=COALESCE(r.recipient_dept_id, pe.department_id)
            LEFT JOIN users fu ON fu.id=r.forwarded_by
            WHERE r.document_id=? ORDER BY r.id", [$docId]);
    }

    /** ส่งต่อให้บุคลากร 1 คน — คืน false ถ้าเคยส่งให้คนนี้แล้ว */
    public function recipientAdd(int $docId, int $personnelId, string $action='forward',
                                 string $instruction='', string $dueDate='', ?int $by=null): bool
    {
        if(!isset(self::ACTIONS[$action])) $action='forward';
        $dup=$this->first("SELECT id FROM document_recipients WHERE document_id=? AND recipient_personnel_id=?",
            [$docId,$personnelId]);
        if($dup) return false;
        $dept=$this->first("SELECT department_id FROM personnel WHERE id=?", [$personnelId]);
        $this->execute("INSERT INTO document_recipients (document_id, recipient_personnel_id, recipient_dept_id,
            action, status, instruction, due_date, forwarded_by, forwarded_at)
            VALUES (?,?,?,?,'pending',?,?,?,NOW())",
            [$docId,$personnelId,$dept['department_id'] ?? null,$action,
             $instruction?:null,$dueDate?:null,$by]);
        return true;
    }

    /** แท็กทั้งฝ่าย — ส่งให้บุคลากรทุกคนในฝ่ายนั้น */
    public function recipientAddDept(int $docId, int $deptId, string $action='forward',
                                     string $instruction='', string $dueDate='', ?int $by=null): int
    {
        $rows=$this->query("SELECT id FROM personnel
            WHERE department_id=? AND deleted_at IS NULL AND status='active'", [$deptId]);
        $n=0;
        foreach($rows as $r){
            if($this->recipientAdd($docId,(int)$r['id'],$action,$instruction,$dueDate,$by)) $n++;
        }
        return $n;
    }

    /** ส่งต่อหลายคนพร้อมกัน */
    public function recipientAddMany(int $docId, array $personnelIds, string $action='forward',
                                     string $instruction='', string $dueDate='', ?int $by=null): int
    {
        $n=0;
        foreach($personnelIds as $pid){
            $pid=(int)$pid;
            if($pid>0 && $this->recipientAdd($docId,$pid,$action,$instruction,$dueDate,$by)) $n++;
        }
        return $n;
    }

    public function recipientFind(int $id): ?array
    { return $this->first("SELECT * FROM document_recipients WHERE id=?", [$id]); }

    public function recipientAck(int $id, string $note=''): void
    {
        $this->execute("UPDATE document_recipients SET status='done', note=?, acted_at=NOW(),
            is_read=1, read_at=COALESCE(read_at,NOW()) WHERE id=?", [$note?:null,$id]);
    }
    public function recipientMarkRead(int $docId, int $personnelId): void
    {
        $this->execute("UPDATE document_recipients SET is_read=1, read_at=COALESCE(read_at,NOW())
            WHERE document_id=? AND recipient_personnel_id=? AND is_read=0", [$docId,$personnelId]);
    }
    public function recipientDelete(int $id): void
    { $this->execute("DELETE FROM document_recipients WHERE id=?", [$id]); }

    public function recipientStats(int $docId): array
    {
        $r=$this->first("SELECT COUNT(*) total, SUM(status='done') done, SUM(is_read=1) read_cnt
            FROM document_recipients WHERE document_id=?", [$docId]);
        $t=(int)($r['total'] ?? 0); $d=(int)($r['done'] ?? 0);
        return ['total'=>$t,'done'=>$d,'read'=>(int)($r['read_cnt'] ?? 0),
                'percent'=>$t>0?round($d/$t*100):0];
    }

    // ---------- ลำดับการดำเนินการ (timeline + stepper) — จดจำไว้จนกว่าจะปิดเรื่อง ----------
    /** บุคลากรคนนี้เป็นผู้รับหนังสือฉบับนี้หรือไม่ (ใช้ให้สิทธิ์เรียกดู/ดาวน์โหลด) */
    public function isRecipient(int $docId, int $personnelId): bool
    {
        return (bool)$this->first("SELECT 1 FROM document_recipients
            WHERE document_id=? AND recipient_personnel_id=? LIMIT 1", [$docId,$personnelId]);
    }
    /** รายการผู้รับของบุคลากรคนนี้ในหนังสือฉบับนี้ (ไว้แสดงปุ่มรับทราบ/ดำเนินการในหน้าติดตาม) */
    public function recipientForPersonnel(int $docId, int $personnelId): ?array
    {
        return $this->first("SELECT r.*, fu.full_name AS forwarder
            FROM document_recipients r LEFT JOIN users fu ON fu.id=r.forwarded_by
            WHERE r.document_id=? AND r.recipient_personnel_id=? ORDER BY r.id DESC LIMIT 1",
            [$docId,$personnelId]);
    }

    /** ขั้นตอนมาตรฐานของหนังสือ (7 ขั้น) + สถานะปัจจุบัน — ใช้ทั้งหน้าจัดการและหน้าติดตาม */
    public function progress(int $docId): array
    {
        $d=$this->find($docId);
        if(!$d) return ['steps'=>[],'done'=>0,'total'=>0,'percent'=>0,'curIdx'=>null];
        $assign=$this->notes($docId,'assign');
        $order=$this->notes($docId,'order');
        $rs=$this->recipientStats($docId);
        $inc=$d['doc_type']==='incoming';
        $ordered=!empty($order)||$d['director_status']!=='pending';
        $fwdTotal=(int)$rs['total']; $fwdDone=(int)$rs['done'];
        $allDone=$fwdTotal>0 && (int)$rs['percent']===100;
        $decN=['approved'=>'อนุมัติ','rejected'=>'ไม่อนุมัติ','noted'=>'ทราบ/สั่งการ','pending'=>''];

        $steps=[
            ['t'=>$inc?'ลงรับหนังสือ':'ลงทะเบียน',
             'done'=>$inc?(bool)$d['received_at']:true,
             'at'=>$inc?$d['received_at']:$d['created_at'],
             'note'=>$inc?($d['received_at']?('เลขรับ '.($d['receive_no']?:'-')):'รอประทับตราลงรับ'):('เลขที่ '.($d['doc_number']?:'-'))],
            ['t'=>'เกษียนเสนอ',
             'done'=>!empty($assign),
             'at'=>$assign[0]['created_at']??null,
             'note'=>$assign?(count($assign).' รายการ · '.($assign[0]['author_name']?:'ธุรการ')):'รอเจ้าหน้าที่ธุรการ'],
            ['t'=>'ผู้บริหารสั่งการ',
             'done'=>$ordered,
             'at'=>$order[0]['created_at']??null,
             'note'=>$ordered?($decN[$d['director_status']]?:'สั่งการแล้ว'):'รอผู้อำนวยการสั่งการ'],
            ['t'=>'ลงลายมือชื่อ',
             'done'=>(bool)(int)$d['is_signed'],
             'at'=>$d['signed_at']??null,
             'note'=>(int)$d['is_signed']?'ลงนามอิเล็กทรอนิกส์แล้ว':'รอลงลายมือชื่อ'],
            ['t'=>'มอบหมาย/ส่งต่อ',
             'done'=>$fwdTotal>0,
             'at'=>null,
             'note'=>$fwdTotal>0?('ส่งต่อ '.$fwdTotal.' ราย · เสร็จ '.$fwdDone.'/'.$fwdTotal):'ยังไม่ส่งต่อ'],
            ['t'=>'ผู้รับดำเนินการ',
             'done'=>$allDone,
             'at'=>null,
             'note'=>$fwdTotal>0?('ดำเนินการแล้ว '.$fwdDone.'/'.$fwdTotal.' ราย'):'—'],
            ['t'=>'เสร็จสิ้น/ปิดเรื่อง',
             'done'=>$d['status']==='completed'||$d['status']==='archived',
             'at'=>($d['status']==='completed'||$d['status']==='archived')?$d['updated_at']:null,
             'note'=>$d['status']==='completed'?'ปิดเรื่องแล้ว':($d['status']==='archived'?'จัดเก็บแล้ว':($allDone?'พร้อมปิดเรื่อง':'รอปิดเรื่อง'))],
        ];
        $curIdx=null; foreach($steps as $i=>$s){ if(empty($s['done'])){ $curIdx=$i; break; } }
        $done=count(array_filter($steps,fn($s)=>!empty($s['done'])));
        $n=count($steps);
        return ['steps'=>$steps,'done'=>$done,'total'=>$n,
                'percent'=>$n>0?(int)round($done/$n*100):0,'curIdx'=>$curIdx];
    }

    /** ประวัติการดำเนินการแบบละเอียด (เรียงตามเวลา) — รวมทุกเหตุการณ์บนหนังสือฉบับนี้ */
    public function timeline(int $docId): array
    {
        $d=$this->first("SELECT d.*, cu.full_name AS creator, ru.full_name AS receiver
            FROM documents d
            LEFT JOIN users cu ON cu.id=d.created_by
            LEFT JOIN users ru ON ru.id=d.received_by
            WHERE d.id=?", [$docId]);
        if(!$d) return [];
        $ev=[];
        $push=function(?string $at,string $icon,string $title,string $detail,string $actor,string $tone) use (&$ev){
            if(!$at) return;
            $ev[]=['at'=>$at,'icon'=>$icon,'title'=>$title,'detail'=>$detail,'actor'=>$actor,'tone'=>$tone];
        };
        $push($d['created_at'],'📝','ลงทะเบียนหนังสือเข้าระบบ',
            $d['doc_number']?('เลขที่ '.$d['doc_number']):'', (string)($d['creator']??''),'done');
        if($d['received_at'])
            $push($d['received_at'],'📮','ลงรับหนังสือ','เลขรับ '.($d['receive_no']?:'-'),
                (string)($d['receiver']??''),'done');
        $decN=['approved'=>'อนุมัติ','rejected'=>'ไม่อนุมัติ','noted'=>'ทราบ/สั่งการ','none'=>''];
        foreach($this->query("SELECT * FROM document_notes WHERE document_id=? ORDER BY id",[$docId]) as $n){
            if($n['kind']==='assign')
                $push($n['created_at'],'✍️','เกษียนเสนอ/มอบหมาย',(string)$n['body'],(string)($n['author_name']??''),'done');
            else
                $push($n['created_at'],'🖋️','ผู้บริหารสั่งการ'.(($decN[$n['decision']]??'')?' ('.$decN[$n['decision']].')':''),
                    (string)$n['body'],(string)($n['author_name']??''),'done');
        }
        foreach($this->recipients($docId) as $r){
            $who=$r['name']?:($r['dept']?:'-');
            $push($r['forwarded_at'],'📨','ส่งต่อถึง '.$who,
                (string)(self::ACTIONS[$r['action']]??$r['action']).($r['instruction']?(' · '.$r['instruction']):''),
                (string)($r['forwarder']??''),'info');
            if($r['status']==='done')
                $push($r['acted_at'],'✅',$who.' ดำเนินการแล้ว',(string)($r['note']??''),'','done');
        }
        if($d['status']==='completed'||$d['status']==='archived')
            $push($d['updated_at'],'🏁',$d['status']==='completed'?'ปิดเรื่อง — ดำเนินการเสร็จสิ้น':'จัดเก็บเข้าแฟ้ม','','','done');
        usort($ev,fn($a,$b)=>strcmp((string)$a['at'],(string)$b['at']));
        return $ev;
    }

    // ---------- หนังสือถึงฉัน ----------
    /** หนังสือที่ถูกส่งต่อ/มอบหมายมาถึงบุคลากรคนนี้ */
    public function inbox(int $personnelId, string $filter='all'): array
    {
        $w='r.recipient_personnel_id=?'; $p=[$personnelId];
        if($filter==='pending')      $w.=" AND r.status='pending'";
        elseif($filter==='done')     $w.=" AND r.status='done'";
        elseif($filter==='overdue')  $w.=" AND r.status='pending' AND r.due_date IS NOT NULL AND r.due_date<CURDATE()";
        return $this->query("SELECT r.id AS recip_id, r.action, r.status, r.instruction, r.due_date,
            r.is_read, r.forwarded_at, r.note AS recip_note,
            fu.full_name AS forwarder,
            d.id, d.doc_number, d.receive_no, d.send_no, d.doc_type, d.title, d.from_org,
            d.doc_date, d.urgency, d.status AS doc_status,
            (SELECT COUNT(*) FROM document_attachments a WHERE a.document_id=d.id) AS file_count
            FROM document_recipients r
            JOIN documents d ON d.id=r.document_id
            LEFT JOIN users fu ON fu.id=r.forwarded_by
            WHERE $w
            ORDER BY r.status='pending' DESC,
                     FIELD(d.urgency,'most_urgent','very_urgent','urgent','normal'),
                     r.due_date IS NULL, r.due_date, r.id DESC", $p);
    }

    public function inboxStats(int $personnelId): array
    {
        $r=$this->first("SELECT COUNT(*) total,
            SUM(status='pending') pending, SUM(is_read=0 AND status='pending') unread,
            SUM(status='pending' AND due_date IS NOT NULL AND due_date<CURDATE()) overdue,
            SUM(status='done') done
            FROM document_recipients WHERE recipient_personnel_id=?", [$personnelId]);
        return ['total'=>(int)($r['total'] ?? 0),'pending'=>(int)($r['pending'] ?? 0),
                'unread'=>(int)($r['unread'] ?? 0),'overdue'=>(int)($r['overdue'] ?? 0),
                'done'=>(int)($r['done'] ?? 0)];
    }

    // ---------- ประวัติการเปิดอ่าน (รวมจาก /official-docs เดิม) ----------
    public function viewLog(int $docId, ?int $userId, ?int $deptId): void
    {
        $this->execute("INSERT INTO document_views (document_id, user_id, department_id) VALUES (?,?,?)",
            [$docId,$userId,$deptId]);
    }
    public function views(int $docId): array
    {
        return $this->query("SELECT v.viewed_at, u.full_name, dep.name AS dept
            FROM document_views v
            LEFT JOIN users u ON u.id=v.user_id
            LEFT JOIN org_departments dep ON dep.id=v.department_id
            WHERE v.document_id=? ORDER BY v.viewed_at DESC LIMIT 50", [$docId]);
    }
    public function viewCount(int $docId): int
    {
        $r=$this->first("SELECT COUNT(*) c FROM document_views WHERE document_id=?", [$docId]);
        return (int)($r['c'] ?? 0);
    }
    public function personnelDept(int $personnelId): ?int
    {
        $r=$this->first("SELECT department_id FROM personnel WHERE id=?", [$personnelId]);
        return $r && $r['department_id'] ? (int)$r['department_id'] : null;
    }

    /** บุคลากรพร้อมชื่อฝ่าย (สำหรับเลือกผู้รับ) */
    public function personnelByDept(): array
    {
        return $this->query("SELECT p.id, CONCAT(p.prefix,p.first_name,' ',p.last_name) AS name,
            p.position, COALESCE(d.name,'ไม่ระบุฝ่าย') AS dept
            FROM personnel p LEFT JOIN org_departments d ON d.id=p.department_id
            WHERE p.deleted_at IS NULL AND p.status='active'
            ORDER BY d.name, p.first_name");
    }
    /** เจ้าหน้าที่ธุรการ (ผู้ถือ role) — สำหรับเติมชื่อผู้เกษียนอัตโนมัติ */
    public function clerk(): ?array
    {
        $r=$this->first("SELECT COALESCE(NULLIF(TRIM(CONCAT(p.prefix,p.first_name,' ',p.last_name)),''), u.full_name) AS name,
                'เจ้าหน้าที่ธุรการ' AS position, u.signature_path
            FROM users u
            JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id
            LEFT JOIN personnel p ON p.id=u.linked_id AND u.linked_type='personnel'
            WHERE r.name='เจ้าหน้าที่ธุรการ' AND u.deleted_at IS NULL
            ORDER BY u.id LIMIT 1");
        return $r ?: null;
    }

    /** ชื่อ+ตำแหน่ง+ลายเซ็นของผู้ใช้ปัจจุบัน (เติมช่องผู้เกษียน/ผู้สั่งการอัตโนมัติ) */
    public function userIdentity(int $userId): array
    {
        $r=$this->first("SELECT COALESCE(NULLIF(TRIM(CONCAT(p.prefix,p.first_name,' ',p.last_name)),''), u.full_name) AS name,
                COALESCE(NULLIF(p.position,''),
                  (SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id ORDER BY ur.role_id LIMIT 1),'') AS position,
                u.signature_path
            FROM users u
            LEFT JOIN personnel p ON p.id=u.linked_id AND u.linked_type='personnel'
            WHERE u.id=?", [$userId]);
        return $r ?: ['name'=>'','position'=>'','signature_path'=>null];
    }

    public function deptsWithCount(): array
    {
        return $this->query("SELECT d.id, d.name,
            (SELECT COUNT(*) FROM personnel p WHERE p.department_id=d.id
              AND p.deleted_at IS NULL AND p.status='active') AS n
            FROM org_departments d ORDER BY d.name");
    }


    // ---------- ไฟล์แนบ ----------
    public function attachments(int $docId): array
    { return $this->query("SELECT * FROM document_attachments WHERE document_id=? ORDER BY id", [$docId]); }

    public function attachmentAdd(int $docId, string $path, string $orig, ?string $mime, int $size, string $note='', ?int $by=null): int
    {
        $this->execute("INSERT INTO document_attachments (document_id, file_path, original_name, mime_type,
            size_bytes, note, uploaded_by) VALUES (?,?,?,?,?,?,?)",
            [$docId,$path,$orig,$mime,$size,$note?:null,$by]);
        return $this->lastId();
    }
    public function attachmentFind(int $id): ?array
    { return $this->first("SELECT * FROM document_attachments WHERE id=?", [$id]); }
    public function attachmentDelete(int $id): void
    { $this->execute("DELETE FROM document_attachments WHERE id=?", [$id]); }

    // ---------- เกษียนหนังสือ ----------
    public function notes(int $docId, ?string $kind=null): array
    {
        $w='n.document_id=?'; $p=[$docId];
        if($kind){ $w.=' AND n.kind=?'; $p[]=$kind; }
        return $this->query("SELECT n.*, CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS assignee_name
            FROM document_notes n
            LEFT JOIN personnel pe ON pe.id=n.assigned_to
            WHERE $w ORDER BY n.id", $p);
    }

    /** เกษียนเสนอ/มอบหมาย (ข้อความสีน้ำเงิน) */
    public function noteAssign(int $docId, array $d, ?int $by=null): int
    {
        $this->execute("INSERT INTO document_notes (document_id, kind, body, author_id, author_name,
            author_position, signature_path, assigned_to, due_date) VALUES (?,'assign',?,?,?,?,?,?,?)",
            [$docId,$d['body'],$by,$d['author_name']?:null,$d['author_position']?:null,
             $d['signature_path']?:null,$d['assigned_to']?:null,$d['due_date']?:null]);
        $id=$this->lastId();
        if(!empty($d['assigned_to']) || !empty($d['due_date']) || !empty($d['assigned_dept'])){
            $this->execute("UPDATE documents SET assigned_to=COALESCE(?,assigned_to),
                assigned_dept=COALESCE(?,assigned_dept), due_date=COALESCE(?,due_date) WHERE id=?",
                [$d['assigned_to']?:null,$d['assigned_dept']?:null,$d['due_date']?:null,$docId]);
        }
        return $id;
    }

    /** คำสั่งการของผู้อำนวยการ + ลงลายมือชื่อ (ข้อความสีน้ำเงิน) */
    public function noteOrder(int $docId, array $d, ?int $by=null): int
    {
        $dec=in_array($d['decision'],['approved','rejected','noted'],true)?$d['decision']:'noted';
        $sign=!empty($d['sign'])?1:0;
        $this->execute("INSERT INTO document_notes (document_id, kind, body, decision, author_id, author_name,
            author_position, signature_path, assigned_to, due_date, is_signed, signed_at)
            VALUES (?,'order',?,?,?,?,?,?,?,?,?,?)",
            [$docId,$d['body'],$dec,$by,$d['author_name']?:null,$d['author_position']?:null,
             $sign?($d['signature_path']?:null):null,
             $d['assigned_to']?:null,$d['due_date']?:null,$sign,$sign?date('Y-m-d H:i:s'):null]);
        $id=$this->lastId();
        $this->execute("UPDATE documents SET director_status=?, assigned_to=COALESCE(?,assigned_to),
            due_date=COALESCE(?,due_date),
            is_signed=GREATEST(is_signed,?), signed_by=IF(?=1,?,signed_by), signed_at=IF(?=1,NOW(),signed_at),
            status=IF(?='approved','in_process',status)
            WHERE id=?",
            [$dec,$d['assigned_to']?:null,$d['due_date']?:null,$sign,$sign,$by,$sign,$dec,$docId]);
        return $id;
    }

    public function noteDelete(int $id): void { $this->execute("DELETE FROM document_notes WHERE id=?", [$id]); }

    public function statusSet(int $id, string $status): void
    {
        if(!in_array($status,['draft','registered','in_process','completed','archived'],true)) return;
        $this->execute("UPDATE documents SET status=? WHERE id=?", [$status,$id]);
    }

    // ---------- ตัวเลือก ----------
    public function personnelList(): array
    { return $this->query("SELECT id, CONCAT(prefix,first_name,' ',last_name) AS name, position
        FROM personnel WHERE deleted_at IS NULL AND status='active' ORDER BY first_name"); }
    public function departments(): array
    { return $this->query("SELECT id, name FROM org_departments ORDER BY name"); }

    // ---------- จัดการฐานข้อมูล / รีเซตระบบ ----------
    /** สถิติปัจจุบันของข้อมูลสารบรรณ (ก่อนรีเซต แสดงให้ผู้ใช้เห็น) */
    public function dataStats(): array
    {
        $one=fn($sql)=>(int)($this->first($sql)['c'] ?? 0);
        return [
            'documents'   => $one("SELECT COUNT(*) c FROM documents"),
            'incoming'    => $one("SELECT COUNT(*) c FROM documents WHERE doc_type='incoming'"),
            'outgoing'    => $one("SELECT COUNT(*) c FROM documents WHERE doc_type IN ('outgoing','circular','internal')"),
            'attachments' => $one("SELECT COUNT(*) c FROM document_attachments"),
            'notes'       => $one("SELECT COUNT(*) c FROM document_notes"),
            'recipients'  => $one("SELECT COUNT(*) c FROM document_recipients"),
            'views'       => $one("SELECT COUNT(*) c FROM document_views"),
        ];
    }

    /** คืน path ไฟล์แนบทั้งหมด (เพื่อลบไฟล์บนดิสก์ก่อนล้าง DB) */
    public function allAttachmentPaths(): array
    {
        $rows=$this->query("SELECT file_path FROM document_attachments");
        return array_column($rows, 'file_path');
    }

    /**
     * ล้างข้อมูลสารบรรณทั้งหมด (หนังสือ/ไฟล์แนบ/เกษียน/ผู้รับ/ประวัติเปิดอ่าน)
     * และรีเซตเลขทะเบียนรับ-ส่งกลับไปเริ่มนับใหม่
     * ไม่แตะ document_templates (คลังแบบฟอร์ม E-Office)
     * @return array นับจำนวนที่ลบไปในแต่ละตาราง
     */
    public function resetAll(): array
    {
        $before=$this->dataStats();
        // ลบ documents พอ ตารางลูกจะหายตาม FK CASCADE
        // แต่ลบตารางลูกก่อนด้วยเพื่อความชัวร์ (บางที่อาจไม่มี FK)
        $this->execute("DELETE FROM document_recipients");
        $this->execute("DELETE FROM document_notes");
        $this->execute("DELETE FROM document_attachments");
        $this->execute("DELETE FROM document_views");
        $this->execute("DELETE FROM documents");
        // รีเซต AUTO_INCREMENT ให้ id เริ่มที่ 1
        foreach(['documents','document_recipients','document_notes','document_attachments','document_views'] as $t){
            try { $this->execute("ALTER TABLE $t AUTO_INCREMENT=1"); } catch (\Throwable $e) {}
        }
        return $before;
    }

}
