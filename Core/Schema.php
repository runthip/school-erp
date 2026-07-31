<?php
namespace App\Core;

/**
 * ตรวจสอบว่าฐานข้อมูลมีตาราง/คอลัมน์ที่โมดูลต้องใช้หรือยัง
 * ใช้กันกรณี "ลืม import ไฟล์ SQL" แล้วหน้าเว็บพัง 500 โดยไม่บอกสาเหตุ
 */
class Schema
{
    /**
     * รายการที่แต่ละโมดูลต้องใช้ → มาจากไฟล์ SQL ไหน
     * เก็บไว้ส่วนกลางเพื่อให้ทั้งตัวกันหน้าพัง และตัวนำเข้าอัตโนมัติ
     * คำนวณ "ไฟล์ที่ขาด" ได้เองจากแหล่งเดียวกัน
     */
    public const REQUIREMENTS = [
        'budget_memo' => [
            '28_budget_memo.sql' => ['tables'=>['budget_memos']],
            '29_budget_unify.sql' => ['columns'=>['budget_memos'=>['paid_at']]],
            '30_budget_rules.sql' => ['columns'=>['budget_memos'=>['deleted_at'],'projects'=>['approval_status']]],
        ],
        'sar' => [
            '27_sar.sql' => ['tables'=>['sar_reports','sar_attachments']],
        ],
        'attendance' => [
            '26_attendance_flag.sql' => ['columns'=>['attendances'=>['attendance_type','arrived_at']]],
        ],
        'documents' => [
            '23_document_flow.sql'     => ['tables'=>['document_attachments','document_notes'],
                                           'columns'=>['documents'=>['receive_no','received_at','director_status','urgency']]],
            '24_document_complete.sql' => ['columns'=>['documents'=>['send_no','sent_at'],
                                           'document_recipients'=>['recipient_personnel_id']]],
            '25_document_routing.sql'  => ['columns'=>['document_recipients'=>['instruction','due_date','is_read','forwarded_by']]],
        ],
    ];

    /** ไฟล์ที่ขาดของโมดูลเดียว */
    public static function missingFor(string $module): array
    {
        return self::missing(self::REQUIREMENTS[$module] ?? []);
    }

    /** ไฟล์ที่ขาดของทุกโมดูล (เรียงตามชื่อไฟล์) */
    public static function missingAll(): array
    {
        $m=[];
        foreach(self::REQUIREMENTS as $req) $m=array_merge($m, self::missing($req));
        $m=array_values(array_unique($m));
        sort($m, SORT_NATURAL);
        return $m;
    }

    /** @var array<string,array<string,bool>> cache ต่อ 1 request */
    private static array $cols = [];
    private static array $tables = [];

    public static function hasTable(string $table): bool
    {
        if(isset(self::$tables[$table])) return self::$tables[$table];
        try {
            $st=Database::pdo()->prepare(
                "SELECT COUNT(*) c FROM information_schema.tables
                 WHERE table_schema=DATABASE() AND table_name=?");
            $st->execute([$table]);
            return self::$tables[$table] = ((int)$st->fetchColumn() > 0);
        } catch (\Throwable $e) { return self::$tables[$table] = false; }
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $k=$table.'.'.$column;
        if(isset(self::$cols[$k])) return self::$cols[$k];
        try {
            $st=Database::pdo()->prepare(
                "SELECT COUNT(*) c FROM information_schema.columns
                 WHERE table_schema=DATABASE() AND table_name=? AND column_name=?");
            $st->execute([$table,$column]);
            return self::$cols[$k] = ((int)$st->fetchColumn() > 0);
        } catch (\Throwable $e) { return self::$cols[$k] = false; }
    }

    /**
     * ตรวจรายการที่ต้องมี แล้วคืนชื่อไฟล์ SQL ที่ยังไม่ได้นำเข้า
     * @param array<string,array{tables?:array<string>,columns?:array<string,array<string>>}> $req
     * @return array<string> รายชื่อไฟล์ที่ต้อง import
     */
    public static function missing(array $req): array
    {
        $miss=[];
        foreach($req as $file=>$need){
            foreach(($need['tables'] ?? []) as $t){
                if(!self::hasTable($t)){ $miss[]=$file; continue 2; }
            }
            foreach(($need['columns'] ?? []) as $t=>$cols){
                foreach($cols as $c){
                    if(!self::hasColumn($t,$c)){ $miss[]=$file; continue 3; }
                }
            }
        }
        return array_values(array_unique($miss));
    }
}
