<?php
namespace App\Models;
use App\Core\Model;

class Personnel extends Model
{
    protected string $table='personnel';
    public function paginate(string $q='', int $limit=20, int $offset=0): array
    {
        $w=['p.deleted_at IS NULL']; $pa=[];
        if($q!==''){ $w[]='(p.employee_code LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.position LIKE ?)'; $l="%$q%"; array_push($pa,$l,$l,$l,$l); }
        $ws='WHERE '.implode(' AND ',$w);
        return $this->query(
          "SELECT p.*, d.name AS dept_name, sg.name AS group_name
           FROM personnel p
           LEFT JOIN org_departments d ON d.id=p.department_id
           LEFT JOIN subject_groups sg ON sg.id=p.subject_group_id
           $ws ORDER BY p.employee_code LIMIT $limit OFFSET $offset", $pa);
    }
    public function countAll(string $q=''): int
    {
        $w=['deleted_at IS NULL']; $p=[];
        if($q!==''){ $w[]='(employee_code LIKE ? OR first_name LIKE ? OR last_name LIKE ?)'; $l="%$q%"; array_push($p,$l,$l,$l); }
        return (int)($this->first("SELECT COUNT(*) c FROM personnel WHERE ".implode(' AND ',$w),$p)['c']??0);
    }
    public function detail(int $id): ?array
    {
        return $this->first(
          "SELECT p.*, d.name AS dept_name, sg.name AS group_name
           FROM personnel p LEFT JOIN org_departments d ON d.id=p.department_id
           LEFT JOIN subject_groups sg ON sg.id=p.subject_group_id WHERE p.id=? LIMIT 1",[$id]);
    }
    public function teaching(int $id): array
    {
        return $this->query(
          "SELECT ta.*, s.name_th AS subject, c.name AS classroom
           FROM teaching_assignments ta JOIN subjects s ON s.id=ta.subject_id
           JOIN classrooms c ON c.id=ta.classroom_id WHERE ta.teacher_id=?",[$id]);
    }
    public function leaves(int $id): array
    {
        return $this->query(
          "SELECT l.*, lt.name AS type_name FROM leaves l JOIN leave_types lt ON lt.id=l.leave_type_id
           WHERE l.personnel_id=? ORDER BY l.start_date DESC LIMIT 10",[$id]);
    }
}
