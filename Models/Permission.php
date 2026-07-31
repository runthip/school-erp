<?php
namespace App\Models;

use App\Core\Model;

class Permission extends Model
{
    protected string $table = 'permissions';

    /** ทั้งหมด จัดกลุ่มตามโมดูล */
    public function grouped(): array
    {
        $rows = $this->query("SELECT * FROM permissions ORDER BY module, code");
        $out = [];
        foreach ($rows as $r) {
            $out[$r['module'] ?: 'อื่น ๆ'][] = $r;
        }
        return $out;
    }

    public function allFlat(): array
    {
        return $this->query("SELECT * FROM permissions ORDER BY module, code");
    }
}
