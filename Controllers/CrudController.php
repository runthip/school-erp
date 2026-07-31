<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Models\AuditLog;

/**
 * CRUD กลางแบบ config-driven — ใช้ config/crud.php
 * routes: GET /crud/{entity}/create, POST /crud/{entity}/create,
 *         GET /crud/{entity}/{id}/edit, POST /crud/{entity}/{id}/edit,
 *         POST /crud/{entity}/{id}/delete
 */
class CrudController extends Controller
{
    private array $configs;

    public function __construct()
    {
        $this->configs = require BASE_PATH . '/config/crud.php';
    }

    private function cfg(string $entity): array
    {
        if (!isset($this->configs[$entity])) {
            http_response_code(404);
            exit('ไม่พบชนิดข้อมูล');
        }
        $cfg = $this->configs[$entity];
        $this->authorize($cfg['perm']);
        return $cfg;
    }

    private function loadOptions(array &$cfg): void
    {
        $pdo = Database::pdo();
        foreach ($cfg['fields'] as &$f) {
            if (isset($f['options_sql'])) {
                [$sql,$idCol,$labelCol] = $f['options_sql'];
                $opts = [];
                foreach ($pdo->query($sql) as $r) $opts[(string)$r[$idCol]] = $r[$labelCol];
                $f['options'] = $opts;
            }
        }
        unset($f);
    }

    private function find(array $cfg, int $id): ?array
    {
        $st = Database::pdo()->prepare("SELECT * FROM {$cfg['table']} WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    private function collect(array $cfg): array
    {
        $data = [];
        foreach ($cfg['fields'] as $f) {
            $v = Request::input($f['name'], null);
            if ($v === '' ) $v = null;
            if (($f['required'] ?? false) && ($v === null || trim((string)$v) === '')) {
                $this->back($cfg['back'], 'error', 'กรุณากรอก: ' . $f['label']);
            }
            // ตรวจค่า select ให้อยู่ในตัวเลือก
            if ($f['type']==='select' && $v !== null && isset($f['options']) && !isset($f['options'][(string)$v])) {
                $v = null;
            }
            $data[$f['name']] = $v;
        }
        return $data;
    }

    // ---------- ฟอร์ม ----------
    public function createForm(string $entity): void
    {
        $cfg = $this->cfg($entity);
        $this->loadOptions($cfg);
        $this->view('crud/form', [
            'title' => 'เพิ่ม' . $cfg['label'],
            'entity' => $entity, 'cfg' => $cfg, 'row' => [], 'isEdit' => false,
        ]);
    }

    public function editForm(string $entity, string $id): void
    {
        $cfg = $this->cfg($entity);
        $row = $this->find($cfg, (int)$id);
        if (!$row) $this->back($cfg['back'], 'error', 'ไม่พบข้อมูล');
        $this->loadOptions($cfg);
        $this->view('crud/form', [
            'title' => 'แก้ไข' . $cfg['label'],
            'entity' => $entity, 'cfg' => $cfg, 'row' => $row, 'isEdit' => true,
        ]);
    }

    // ---------- บันทึก ----------
    public function store(string $entity): void
    {
        $cfg = $this->cfg($entity);
        $this->verifyCsrf();
        $this->loadOptions($cfg);
        $data = array_merge($cfg['defaults'] ?? [], $this->collect($cfg));
        $data = array_filter($data, fn($v) => $v !== null); // ปล่อยให้คอลัมน์ที่ไม่กรอกใช้ค่า default ของฐานข้อมูล
        $cols = array_keys($data);
        $sql = "INSERT INTO {$cfg['table']} (" . implode(',', $cols) . ") VALUES (" . rtrim(str_repeat('?,', count($cols)), ',') . ")";
        try {
            $st = Database::pdo()->prepare($sql);
            $st->execute(array_values($data));
            $newId = (int)Database::pdo()->lastInsertId();
            AuditLog::record(Auth::id(), 'create', $cfg['table'], $newId);
            $this->back($cfg['back'], 'success', 'เพิ่ม' . $cfg['label'] . 'เรียบร้อยแล้ว');
        } catch (\PDOException $e) {
            $msg = str_contains($e->getMessage(), 'Duplicate') ? 'ข้อมูลซ้ำ (รหัสนี้มีอยู่แล้ว)' : 'บันทึกไม่สำเร็จ: ข้อมูลไม่ถูกต้อง';
            $this->back($cfg['back'], 'error', $msg);
        }
    }

    public function update(string $entity, string $id): void
    {
        $cfg = $this->cfg($entity);
        $this->verifyCsrf();
        if (!$this->find($cfg, (int)$id)) $this->back($cfg['back'], 'error', 'ไม่พบข้อมูล');
        $this->loadOptions($cfg);
        $data = array_filter($this->collect($cfg), fn($v) => $v !== null); // ข้ามช่องว่าง (ไม่ทับค่าเดิมด้วย NULL)
        if (!$data) $this->back($cfg['back'], 'error', 'ไม่มีข้อมูลให้บันทึก');
        $set = implode(',', array_map(fn($c) => "$c=?", array_keys($data)));
        try {
            $st = Database::pdo()->prepare("UPDATE {$cfg['table']} SET $set WHERE id=?");
            $st->execute([...array_values($data), (int)$id]);
            AuditLog::record(Auth::id(), 'update', $cfg['table'], (int)$id);
            $this->back($cfg['back'], 'success', 'แก้ไข' . $cfg['label'] . 'เรียบร้อยแล้ว');
        } catch (\PDOException $e) {
            $msg = str_contains($e->getMessage(), 'Duplicate') ? 'ข้อมูลซ้ำ (รหัสนี้มีอยู่แล้ว)' : 'บันทึกไม่สำเร็จ: ข้อมูลไม่ถูกต้อง';
            $this->back($cfg['back'], 'error', $msg);
        }
    }

    // ---------- ลบ ----------
    public function delete(string $entity, string $id): void
    {
        $cfg = $this->cfg($entity);
        $this->verifyCsrf();
        if (!$this->find($cfg, (int)$id)) $this->back($cfg['back'], 'error', 'ไม่พบข้อมูล');
        try {
            if ($cfg['soft'] ?? false) {
                Database::pdo()->prepare("UPDATE {$cfg['table']} SET deleted_at=NOW() WHERE id=?")->execute([(int)$id]);
            } else {
                Database::pdo()->prepare("DELETE FROM {$cfg['table']} WHERE id=?")->execute([(int)$id]);
            }
            AuditLog::record(Auth::id(), 'delete', $cfg['table'], (int)$id);
            $this->back($cfg['back'], 'success', 'ลบ' . $cfg['label'] . 'เรียบร้อยแล้ว');
        } catch (\PDOException $e) {
            $this->back($cfg['back'], 'error', 'ลบไม่ได้: มีข้อมูลอื่นอ้างอิงอยู่');
        }
    }
}
