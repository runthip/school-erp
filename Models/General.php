<?php
namespace App\Models;
use App\Core\Model;

class General extends Model
{
    protected string $table = 'assets';

    private function yearBe(): int { return (int)date('Y')+543; }

    // ---------- แจ้งซ่อม / อาคารสถานที่ ----------
    public function repairs(array $f=[]): array
    {
        $w=[]; $p=[];
        if(!empty($f['status'])){ $w[]='r.status=?'; $p[]=$f['status']; }
        if(!empty($f['priority'])){ $w[]='r.priority=?'; $p[]=$f['priority']; }
        $where=$w?('WHERE '.implode(' AND ',$w)):'';
        return $this->query("SELECT r.*, rm.name AS room_name, a.name AS asset_name,
            CONCAT(rep.prefix,rep.first_name,' ',rep.last_name) AS reporter_name,
            CONCAT(asg.prefix,asg.first_name,' ',asg.last_name) AS assignee_name
            FROM repair_requests r
            LEFT JOIN rooms rm ON rm.id=r.room_id
            LEFT JOIN assets a ON a.id=r.asset_id
            LEFT JOIN personnel rep ON rep.id=r.reporter_id
            LEFT JOIN personnel asg ON asg.id=r.assignee_id
            $where ORDER BY FIELD(r.status,'reported','assigned','in_progress','done','cancelled'), r.reported_at DESC", $p);
    }
    public function repairFind(int $id): ?array
    {
        return $this->first("SELECT r.*, rm.name AS room_name, a.name AS asset_name,
            CONCAT(rep.prefix,rep.first_name,' ',rep.last_name) AS reporter_name,
            CONCAT(asg.prefix,asg.first_name,' ',asg.last_name) AS assignee_name
            FROM repair_requests r
            LEFT JOIN rooms rm ON rm.id=r.room_id LEFT JOIN assets a ON a.id=r.asset_id
            LEFT JOIN personnel rep ON rep.id=r.reporter_id LEFT JOIN personnel asg ON asg.id=r.assignee_id
            WHERE r.id=?", [$id]);
    }
    public function repairCreate(array $d): int
    {
        $this->execute("INSERT INTO repair_requests (school_id, reporter_id, room_id, asset_id, title, description, priority, status, reported_at)
            VALUES (1,?,?,?,?,?,?, 'reported', NOW())",
            [$d['reporter_id']?:null,$d['room_id']?:null,$d['asset_id']?:null,$d['title'],$d['description']?:null,$d['priority']]);
        return $this->lastId();
    }
    public function repairUpdate(int $id, array $d): void
    {
        $completed = $d['status']==='done' ? 'NOW()' : 'completed_at';
        $this->execute("UPDATE repair_requests SET title=?, description=?, priority=?, status=?, assignee_id=?, completed_at=".($d['status']==='done'?'NOW()':'completed_at')." WHERE id=?",
            [$d['title'],$d['description']?:null,$d['priority'],$d['status'],$d['assignee_id']?:null,$id]);
    }
    public function repairDelete(int $id): void { $this->execute("DELETE FROM repair_requests WHERE id=?", [$id]); }

    // ---------- พัสดุ / ครุภัณฑ์ ----------
    public function assets(array $f=[]): array
    {
        $w=[]; $p=[];
        if(!empty($f['category'])){ $w[]='a.category_id=?'; $p[]=$f['category']; }
        if(!empty($f['condition'])){ $w[]='a.condition_status=?'; $p[]=$f['condition']; }
        if(!empty($f['q'])){ $w[]='(a.name LIKE ? OR a.asset_code LIKE ?)'; $p[]='%'.$f['q'].'%'; $p[]='%'.$f['q'].'%'; }
        $where=$w?('WHERE '.implode(' AND ',$w)):'';
        return $this->query("SELECT a.*, c.name AS category_name, rm.name AS room_name,
            CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS responsible_name
            FROM assets a
            LEFT JOIN asset_categories c ON c.id=a.category_id
            LEFT JOIN rooms rm ON rm.id=a.location_room_id
            LEFT JOIN personnel pe ON pe.id=a.responsible_id
            $where ORDER BY a.asset_code", $p);
    }
    public function assetFind(int $id): ?array
    {
        return $this->first("SELECT a.*, c.name AS category_name, rm.name AS room_name,
            CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS responsible_name
            FROM assets a LEFT JOIN asset_categories c ON c.id=a.category_id
            LEFT JOIN rooms rm ON rm.id=a.location_room_id LEFT JOIN personnel pe ON pe.id=a.responsible_id
            WHERE a.id=?", [$id]);
    }
    public function assetCreate(array $d): int
    {
        $this->execute("INSERT INTO assets (school_id, asset_code, barcode, category_id, name, spec, model,
                acquired_date, acquired_price, useful_life_years, vendor, vendor_phone, doc_no, fund_type, acquire_method,
                location_room_id, responsible_id, condition_status)
            VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$d['asset_code'],$d['barcode']?:null,$d['category_id']?:null,$d['name'],$d['spec']?:null,$d['model']?:null,
             $d['acquired_date']?:null,$d['acquired_price']?:0,$d['useful_life_years']?:null,
             $d['vendor']?:null,$d['vendor_phone']?:null,$d['doc_no']?:null,$d['fund_type']?:null,$d['acquire_method']?:null,
             $d['location_room_id']?:null,$d['responsible_id']?:null,$d['condition_status']]);
        return $this->lastId();
    }
    public function assetUpdate(int $id, array $d): void
    {
        $this->execute("UPDATE assets SET asset_code=?, barcode=?, category_id=?, name=?, spec=?, model=?,
                acquired_date=?, acquired_price=?, useful_life_years=?, vendor=?, vendor_phone=?, doc_no=?, fund_type=?, acquire_method=?,
                location_room_id=?, responsible_id=?, condition_status=? WHERE id=?",
            [$d['asset_code'],$d['barcode']?:null,$d['category_id']?:null,$d['name'],$d['spec']?:null,$d['model']?:null,
             $d['acquired_date']?:null,$d['acquired_price']?:0,$d['useful_life_years']?:null,
             $d['vendor']?:null,$d['vendor_phone']?:null,$d['doc_no']?:null,$d['fund_type']?:null,$d['acquire_method']?:null,
             $d['location_room_id']?:null,$d['responsible_id']?:null,$d['condition_status'],$id]);
    }
    public function assetDelete(int $id): void { $this->execute("DELETE FROM assets WHERE id=?", [$id]); }
    /** แก้เฉพาะสถานที่ตั้ง (ฝ่ายบริหารทั่วไป) */
    public function assetSetLocation(int $id, ?int $roomId): void
    { $this->execute("UPDATE assets SET location_room_id=? WHERE id=?", [$roomId?:null, $id]); }
    public function assetCategories(): array { return $this->query("SELECT id, code, name FROM asset_categories ORDER BY code"); }

    /** รายงานครุภัณฑ์ตามช่วง (รายเดือน/รายไตรมาส/รายปี) */
    public function assetReport(string $from, string $to): array
    {
        // ครุภัณฑ์ที่ได้มาในช่วง
        $acquired=$this->query("SELECT a.*, c.name AS category_name, rm.name AS room_name
            FROM assets a LEFT JOIN asset_categories c ON c.id=a.category_id
            LEFT JOIN rooms rm ON rm.id=a.location_room_id
            WHERE a.acquired_date BETWEEN ? AND ? ORDER BY a.acquired_date, a.asset_code", [$from,$to]);
        // สรุปตามหมวด (ทั้งหมด ณ สิ้นงวด)
        $byCat=$this->query("SELECT COALESCE(c.name,'ไม่ระบุหมวด') AS name, COUNT(*) cnt,
            COALESCE(SUM(a.acquired_price),0) val
            FROM assets a LEFT JOIN asset_categories c ON c.id=a.category_id
            WHERE a.acquired_date IS NULL OR a.acquired_date<=?
            GROUP BY c.id, c.name ORDER BY val DESC", [$to]);
        // สรุปตามสภาพ
        $byCond=$this->query("SELECT a.condition_status s, COUNT(*) cnt, COALESCE(SUM(a.acquired_price),0) val
            FROM assets a WHERE a.acquired_date IS NULL OR a.acquired_date<=?
            GROUP BY a.condition_status", [$to]);
        // งานซ่อมในช่วง
        $repairs=$this->query("SELECT r.*, rm.name AS room_name FROM repair_requests r
            LEFT JOIN rooms rm ON rm.id=r.room_id
            WHERE DATE(r.reported_at) BETWEEN ? AND ? ORDER BY r.reported_at", [$from,$to]);
        $sum=$this->first("SELECT COUNT(*) cnt, COALESCE(SUM(acquired_price),0) val FROM assets
            WHERE acquired_date BETWEEN ? AND ?", [$from,$to]);
        $all=$this->first("SELECT COUNT(*) cnt, COALESCE(SUM(acquired_price),0) val FROM assets
            WHERE acquired_date IS NULL OR acquired_date<=?", [$to]);
        return ['acquired'=>$acquired,'byCat'=>$byCat,'byCond'=>$byCond,'repairs'=>$repairs,
                'sum'=>$sum,'all'=>$all,'from'=>$from,'to'=>$to];
    }

    // ---------- งานสารบรรณ ----------
    public function documents(array $f=[]): array
    {
        $w=[]; $p=[];
        if(!empty($f['type'])){ $w[]='doc_type=?'; $p[]=$f['type']; }
        if(!empty($f['status'])){ $w[]='status=?'; $p[]=$f['status']; }
        if(!empty($f['q'])){ $w[]='(title LIKE ? OR doc_number LIKE ?)'; $p[]='%'.$f['q'].'%'; $p[]='%'.$f['q'].'%'; }
        $where=$w?('WHERE '.implode(' AND ',$w)):'';
        return $this->query("SELECT * FROM documents $where ORDER BY COALESCE(received_date,doc_date) DESC, id DESC", $p);
    }
    public function documentFind(int $id): ?array { return $this->first("SELECT * FROM documents WHERE id=?", [$id]); }
    public function documentCreate(array $d): int
    {
        $this->execute("INSERT INTO documents (school_id, doc_number, doc_type, title, from_org, to_org, doc_date, received_date, status, is_signed, created_by)
            VALUES (1,?,?,?,?,?,?,?,?,?,?)",
            [$d['doc_number'],$d['doc_type'],$d['title'],$d['from_org']?:null,$d['to_org']?:null,
             $d['doc_date']?:null,$d['received_date']?:null,$d['status'],$d['is_signed']?1:0,$d['created_by']?:null]);
        return $this->lastId();
    }
    public function documentUpdate(int $id, array $d): void
    {
        $this->execute("UPDATE documents SET doc_number=?, doc_type=?, title=?, from_org=?, to_org=?, doc_date=?, received_date=?, status=?, is_signed=? WHERE id=?",
            [$d['doc_number'],$d['doc_type'],$d['title'],$d['from_org']?:null,$d['to_org']?:null,
             $d['doc_date']?:null,$d['received_date']?:null,$d['status'],$d['is_signed']?1:0,$id]);
    }
    public function documentDelete(int $id): void { $this->execute("DELETE FROM documents WHERE id=?", [$id]); }

    // ---------- ยานพาหนะ ----------
    public function vehicles(): array { return $this->query("SELECT * FROM vehicles ORDER BY plate_no"); }
    public function vehicleFind(int $id): ?array { return $this->first("SELECT * FROM vehicles WHERE id=?", [$id]); }
    public function vehicleCreate(array $d): int
    {
        $this->execute("INSERT INTO vehicles (plate_no, brand, vehicle_type, seats, fuel_type, status, note) VALUES (?,?,?,?,?,?,?)",
            [$d['plate_no'],$d['brand']?:null,$d['vehicle_type']?:null,$d['seats']?:null,$d['fuel_type']?:null,$d['status'],$d['note']?:null]);
        return $this->lastId();
    }
    public function vehicleUpdate(int $id, array $d): void
    {
        $this->execute("UPDATE vehicles SET plate_no=?, brand=?, vehicle_type=?, seats=?, fuel_type=?, status=?, note=? WHERE id=?",
            [$d['plate_no'],$d['brand']?:null,$d['vehicle_type']?:null,$d['seats']?:null,$d['fuel_type']?:null,$d['status'],$d['note']?:null,$id]);
    }
    public function vehicleDelete(int $id): void { $this->execute("DELETE FROM vehicles WHERE id=?", [$id]); }

    public function bookings(array $f=[]): array
    {
        $w=[]; $p=[];
        if(!empty($f['status'])){ $w[]='b.status=?'; $p[]=$f['status']; }
        $where=$w?('WHERE '.implode(' AND ',$w)):'';
        return $this->query("SELECT b.*, v.plate_no, v.vehicle_type, u.username AS requester,
            CONCAT(dr.prefix,dr.first_name,' ',dr.last_name) AS driver_name
            FROM vehicle_bookings b
            LEFT JOIN vehicles v ON v.id=b.vehicle_id
            LEFT JOIN users u ON u.id=b.requester_id
            LEFT JOIN personnel dr ON dr.id=b.driver_id
            $where ORDER BY b.depart_at DESC", $p);
    }
    public function bookingFind(int $id): ?array
    {
        return $this->first("SELECT b.*, v.plate_no, v.vehicle_type, u.username AS requester,
            CONCAT(dr.prefix,dr.first_name,' ',dr.last_name) AS driver_name
            FROM vehicle_bookings b LEFT JOIN vehicles v ON v.id=b.vehicle_id
            LEFT JOIN users u ON u.id=b.requester_id LEFT JOIN personnel dr ON dr.id=b.driver_id
            WHERE b.id=?", [$id]);
    }
    public function bookingNo(): string
    {
        $y=$this->yearBe();
        $n=(int)($this->first("SELECT COUNT(*) c FROM vehicle_bookings WHERE booking_no LIKE ?", ['ยพ-'.$y.'-%'])['c']??0)+1;
        return sprintf('ยพ-%d-%04d',$y,$n);
    }
    public function bookingCreate(array $d): int
    {
        $this->execute("INSERT INTO vehicle_bookings (booking_no, vehicle_id, requester_id, purpose, destination, depart_at, return_at, passengers, driver_id, status, note)
            VALUES (?,?,?,?,?,?,?,?,?, 'pending', ?)",
            [$this->bookingNo(),$d['vehicle_id']?:null,$d['requester_id']?:null,$d['purpose'],$d['destination']?:null,
             $d['depart_at'],$d['return_at']?:null,$d['passengers']?:null,$d['driver_id']?:null,$d['note']?:null]);
        return $this->lastId();
    }
    public function bookingDecide(int $id, string $status, ?int $approverId): void
    {
        $this->execute("UPDATE vehicle_bookings SET status=?, approver_id=?, approved_at=NOW() WHERE id=?", [$status,$approverId,$id]);
        // ถ้าอนุมัติ → รถกำลังใช้งาน
        if($status==='approved'){
            $b=$this->bookingFind($id);
            if($b && $b['vehicle_id']) $this->execute("UPDATE vehicles SET status='in_use' WHERE id=?", [$b['vehicle_id']]);
        }
    }
    public function bookingComplete(int $id): void
    {
        $b=$this->bookingFind($id);
        $this->execute("UPDATE vehicle_bookings SET status='completed' WHERE id=?", [$id]);
        if($b && $b['vehicle_id']) $this->execute("UPDATE vehicles SET status='available' WHERE id=?", [$b['vehicle_id']]);
    }
    public function bookingDelete(int $id): void { $this->execute("DELETE FROM vehicle_bookings WHERE id=?", [$id]); }
    public function availableVehicles(): array { return $this->query("SELECT id, plate_no, vehicle_type, seats FROM vehicles WHERE status='available' ORDER BY plate_no"); }

    // ---------- ตัวช่วย ----------
    public function rooms(): array { return $this->query("SELECT id, name FROM rooms ORDER BY name"); }
    public function personnelList(): array
    { return $this->query("SELECT id, CONCAT(prefix,first_name,' ',last_name) AS name FROM personnel WHERE deleted_at IS NULL AND status='active' ORDER BY first_name"); }

    // ---------- แดชบอร์ด ----------
    public function dashboard(): array
    {
        $repairOpen=(int)($this->first("SELECT COUNT(*) c FROM repair_requests WHERE status IN ('reported','assigned','in_progress')")['c']??0);
        $assetTotal=(int)($this->first("SELECT COUNT(*) c FROM assets")['c']??0);
        $assetValue=(float)($this->first("SELECT COALESCE(SUM(acquired_price),0) v FROM assets")['v']??0);
        $docPending=(int)($this->first("SELECT COUNT(*) c FROM documents WHERE status IN ('in_process','pending')")['c']??0);
        $vehicleAvail=(int)($this->first("SELECT COUNT(*) c FROM vehicles WHERE status='available'")['c']??0);
        $bookingPending=(int)($this->first("SELECT COUNT(*) c FROM vehicle_bookings WHERE status='pending'")['c']??0);
        return compact('repairOpen','assetTotal','assetValue','docPending','vehicleAvail','bookingPending');
    }
}
