<?php
namespace App\Models;
use App\Core\Model;

/** โมเดลรวมสำหรับหน้ารายการอ่านอย่างเดียว (วิชา/งบ/พัสดุ/สารบรรณ) */
class Catalog extends Model
{
    protected string $table='';

    public function subjects(string $q='', string $group='', string $type=''): array
    {
        $w=['1=1']; $p=[];
        if($q!==''){ $w[]='(s.subject_code LIKE ? OR s.name_th LIKE ?)'; $l="%$q%"; array_push($p,$l,$l); }
        if($group!==''){ $w[]='s.subject_group_id=?'; $p[]=$group; }
        if($type!==''){ $w[]='s.subject_type=?'; $p[]=$type; }
        $ws='WHERE '.implode(' AND ',$w);
        return $this->query("SELECT s.*, sg.name AS group_name FROM subjects s LEFT JOIN subject_groups sg ON sg.id=s.subject_group_id $ws ORDER BY s.subject_code",$p);
    }
    public function subjectGroups(): array { return $this->query("SELECT id, name FROM subject_groups ORDER BY id"); }
    public function budgets(): array
    {
        return $this->query("SELECT b.*, bs.name AS source_name, ROUND(b.used_amount/NULLIF(b.allocated_amount,0)*100,1) AS used_pct
                             FROM budgets b JOIN budget_sources bs ON bs.id=b.budget_source_id ORDER BY b.id");
    }
    public function projects(string $q='', string $status=''): array
    {
        $w=['1=1']; $p=[];
        if($q!==''){ $w[]='(pr.name LIKE ? OR pr.code LIKE ?)'; $l="%$q%"; array_push($p,$l,$l); }
        if($status!==''){ $w[]='pr.status=?'; $p[]=$status; }
        $ws='WHERE '.implode(' AND ',$w);
        return $this->query("SELECT pr.*, per.first_name, per.last_name FROM projects pr LEFT JOIN personnel per ON per.id=pr.responsible_id $ws ORDER BY pr.id",$p);
    }
    public function assets(string $q='', string $cat='', string $cond=''): array
    {
        $w=['1=1']; $p=[];
        if($q!==''){ $w[]='(a.asset_code LIKE ? OR a.name LIKE ? OR a.barcode LIKE ?)'; $l="%$q%"; array_push($p,$l,$l,$l); }
        if($cat!==''){ $w[]='a.category_id=?'; $p[]=$cat; }
        if($cond!==''){ $w[]='a.condition_status=?'; $p[]=$cond; }
        $ws='WHERE '.implode(' AND ',$w);
        return $this->query("SELECT a.*, ac.name AS cat_name, r.name AS room_name FROM assets a
                             LEFT JOIN asset_categories ac ON ac.id=a.category_id
                             LEFT JOIN rooms r ON r.id=a.location_room_id $ws ORDER BY a.asset_code",$p);
    }
    public function assetCategories(): array { return $this->query("SELECT * FROM asset_categories ORDER BY code"); }
    public function materials(): array { return $this->query("SELECT * FROM materials ORDER BY code"); }
    public function documents(string $q='', string $type='', string $status=''): array
    {
        $w=['1=1']; $p=[];
        if($q!==''){ $w[]='(title LIKE ? OR doc_number LIKE ? OR from_org LIKE ?)'; $l="%$q%"; array_push($p,$l,$l,$l); }
        if($type!==''){ $w[]='doc_type=?'; $p[]=$type; }
        if($status!==''){ $w[]='status=?'; $p[]=$status; }
        $ws='WHERE '.implode(' AND ',$w);
        return $this->query("SELECT * FROM documents $ws ORDER BY doc_date DESC, id DESC",$p);
    }
}
