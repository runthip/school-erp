<?php
namespace App\Core;

use PDO;

/**
 * ตัวนำเข้าไฟล์ SQL จากในระบบ (แทนการให้ผู้ใช้ import เองด้วย phpMyAdmin)
 *
 * รองรับสิ่งที่ PDO ทำเองไม่ได้:
 *  - DELIMITER // ... // (ใช้สร้าง stored procedure ในไฟล์ migration)
 *  - แยกคำสั่งโดยรู้จัก string/comment จึงไม่ตัดผิดเมื่อมี ';' อยู่ในข้อความ
 *  - ข้าม USE <db>; และแทนชื่อฐานข้อมูลที่ฮาร์ดโค้ดไว้ ให้ตรงกับ DB ที่เชื่อมต่อจริง
 */
class Migrator
{
    /** ไฟล์ที่นำเข้าแล้วจะถูกบันทึกไว้ที่ตารางนี้ */
    private const TABLE = 'schema_migrations';

    public static function dir(): string { return BASE_PATH . '/database'; }

    /** สร้างตารางบันทึกประวัติ (ถ้ายังไม่มี) */
    public static function ensureTable(): void
    {
        Database::pdo()->exec(
            "CREATE TABLE IF NOT EXISTS ".self::TABLE." (
               filename   VARCHAR(191) NOT NULL,
               statements INT UNSIGNED NOT NULL DEFAULT 0,
               applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
               PRIMARY KEY (filename)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @return array<string> ไฟล์ .sql ทั้งหมด เรียงตามชื่อ */
    public static function files(): array
    {
        $f = glob(self::dir().'/*.sql') ?: [];
        $f = array_map('basename', $f);
        sort($f, SORT_NATURAL);
        return $f;
    }

    /** @return array<string,array{applied_at:string,statements:int}> */
    public static function applied(): array
    {
        self::ensureTable();
        $rows = Database::pdo()->query("SELECT filename, statements, applied_at FROM ".self::TABLE)
                ->fetchAll(PDO::FETCH_ASSOC);
        $out=[];
        foreach($rows as $r) $out[$r['filename']] = ['applied_at'=>$r['applied_at'],'statements'=>(int)$r['statements']];
        return $out;
    }

    /** @return array<string> ไฟล์ที่ยังไม่เคยนำเข้าผ่านระบบนี้ */
    public static function pending(): array
    {
        $done = self::applied();
        return array_values(array_filter(self::files(), fn($f)=>!isset($done[$f])));
    }

    /**
     * แยกไฟล์ SQL เป็นคำสั่งย่อย โดยรู้จัก DELIMITER / string / comment
     * @return array<string>
     */
    public static function parse(string $sql): array
    {
        $out=[]; $buf=''; $delim=';';
        $i=0; $n=strlen($sql);
        $q=null;              // ' " ` ที่กำลังเปิดอยู่
        while($i < $n){
            $c=$sql[$i];

            // อยู่ใน string
            if($q !== null){
                $buf .= $c;
                if($c === '\\' && $q !== '`' && $i+1 < $n){ $buf .= $sql[$i+1]; $i+=2; continue; }
                if($c === $q){
                    // '' หรือ "" คือ escape ของตัวมันเอง
                    if($i+1 < $n && $sql[$i+1] === $q){ $buf .= $sql[$i+1]; $i+=2; continue; }
                    $q=null;
                }
                $i++; continue;
            }

            // เริ่ม string
            if($c === "'" || $c === '"' || $c === '`'){ $q=$c; $buf.=$c; $i++; continue; }

            // comment แบบบรรทัด
            if(($c === '-' && substr($sql,$i,2) === '--' && (($sql[$i+2] ?? ' ')===' ' || ($sql[$i+2] ?? "\n")==="\n"))
               || $c === '#'){
                $e=strpos($sql,"\n",$i); if($e===false) break;
                $i=$e+1; $buf.="\n"; continue;
            }
            // comment แบบบล็อก
            if($c === '/' && substr($sql,$i,2) === '/*'){
                $e=strpos($sql,'*/',$i+2); if($e===false) break;
                $i=$e+2; continue;
            }

            // DELIMITER (ต้องอยู่ต้นบรรทัด)
            if(($c === 'D' || $c === 'd') && ($i===0 || $sql[$i-1] === "\n")
               && preg_match('/^DELIMITER[ \t]+(\S+)[ \t]*\r?\n/i', substr($sql,$i), $m)){
                if(trim($buf) !== '') { $out[]=trim($buf); $buf=''; }
                $delim=$m[1];
                $i += strlen($m[0]);
                continue;
            }

            // เจอตัวคั่นคำสั่ง
            if(substr($sql,$i,strlen($delim)) === $delim){
                if(trim($buf) !== '') $out[]=trim($buf);
                $buf=''; $i += strlen($delim); continue;
            }

            $buf .= $c; $i++;
        }
        if(trim($buf) !== '') $out[]=trim($buf);

        // ตัดคำสั่งว่าง
        return array_values(array_filter($out, fn($s)=>trim($s) !== '' && !preg_match('/^[\s;]*$/',$s)));
    }


    /**
     * ไฟล์นี้รันซ้ำบนฐานข้อมูลที่มีข้อมูลแล้วได้อย่างปลอดภัยไหม
     * ไม่ปลอดภัยถ้ามี CREATE TABLE ที่ไม่มี IF NOT EXISTS
     * หรือ INSERT ที่ไม่มี IGNORE / NOT EXISTS / ON DUPLICATE KEY
     */
    public static function isRerunnable(string $file): bool
    {
        $path=self::dir().'/'.basename($file);
        if(!is_file($path)) return false;
        $sql=file_get_contents($path) ?: '';
        $stmts=self::parse($sql);

        // ตารางชั่วคราวถูกสร้าง/ลบใหม่ทุกรอบ จึง INSERT ซ้ำได้ไม่มีปัญหา
        $temp=[];
        foreach($stmts as $s){
            if(preg_match('/^\s*CREATE\s+TEMPORARY\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i',$s,$m))
                $temp[strtolower($m[1])]=true;
        }

        foreach($stmts as $s){
            // CREATE TABLE ที่ไม่มี IF NOT EXISTS → รันซ้ำแล้วพัง
            if(preg_match('/^\s*CREATE\s+TABLE\s+(?!IF\s+NOT\s+EXISTS)/i',$s)) return false;

            // INSERT ที่ไม่กันซ้ำ → รันซ้ำแล้วข้อมูลซ้ำ (ยกเว้นตารางชั่วคราว)
            if(preg_match('/^\s*INSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?/i',$s,$m)){
                if(isset($temp[strtolower($m[1])])) continue;
                if(preg_match('/^\s*INSERT\s+IGNORE\b/i',$s)) continue;
                if(preg_match('/\bON\s+DUPLICATE\s+KEY\b/i',$s)) continue;
                if(preg_match('/\bNOT\s+EXISTS\b/i',$s)) continue;
                return false;
            }
        }
        return true;
    }

    /** ทำเครื่องหมายว่านำเข้าแล้ว โดยไม่รันจริง (สำหรับไฟล์ที่ import เองมาก่อน) */
    public static function markApplied(string $file): void
    {
        self::ensureTable();
        $st=Database::pdo()->prepare("INSERT INTO ".self::TABLE." (filename, statements) VALUES (?,0)
                                      ON DUPLICATE KEY UPDATE applied_at=NOW()");
        $st->execute([basename($file)]);
    }

    /** ฐานข้อมูลนี้ติดตั้งใหม่เอี่ยมหรือมีข้อมูลอยู่แล้ว */
    public static function isFreshDatabase(): bool
    {
        try {
            $n=(int)Database::pdo()->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema=DATABASE() AND table_name IN ('users','students','documents')"
            )->fetchColumn();
            return $n === 0;
        } catch (\Throwable $e) { return false; }
    }

    /**
     * นำเข้าไฟล์เดียว
     * @return array{file:string,ok:bool,statements:int,error:?string,skipped:bool}
     */
    public static function runFile(string $file): array
    {
        $path = self::dir().'/'.basename($file);
        if(!is_file($path))
            return ['file'=>$file,'ok'=>false,'statements'=>0,'error'=>'ไม่พบไฟล์','skipped'=>false];

        $sql = file_get_contents($path);
        if($sql === false)
            return ['file'=>$file,'ok'=>false,'statements'=>0,'error'=>'อ่านไฟล์ไม่ได้','skipped'=>false];

        $pdo = Database::pdo();
        $db  = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();

        // ไฟล์ SQL ฮาร์ดโค้ดชื่อ school_erp ไว้ (เช่น table_schema='school_erp')
        // ถ้าผู้ใช้ตั้งชื่อฐานข้อมูลอื่น ต้องแทนให้ตรงกับที่เชื่อมต่ออยู่จริง
        if($db !== '' && $db !== 'school_erp')
            $sql = str_replace("'school_erp'", $pdo->quote($db), $sql);

        $stmts = self::parse($sql);
        $count = 0;
        try {
            $pdo->exec("SET NAMES utf8mb4");
            foreach($stmts as $s){
                // ข้าม USE / CREATE DATABASE — เชื่อมต่อฐานข้อมูลถูกตัวอยู่แล้ว
                if(preg_match('/^\s*(USE|CREATE\s+DATABASE)\b/i', $s)) continue;
                $st = $pdo->query($s);
                if($st instanceof \PDOStatement){
                    // ระบาย result set ให้หมด ไม่งั้น query ถัดไปจะพัง
                    do { try { $st->fetchAll(); } catch (\Throwable $e) {} } while ($st->nextRowset());
                    $st->closeCursor();
                }
                $count++;
            }
        } catch (\Throwable $e) {
            return ['file'=>$file,'ok'=>false,'statements'=>$count,
                    'error'=>$e->getMessage(),'skipped'=>false];
        }

        self::ensureTable();
        $ins=$pdo->prepare("INSERT INTO ".self::TABLE." (filename, statements) VALUES (?,?)
                            ON DUPLICATE KEY UPDATE statements=VALUES(statements), applied_at=NOW()");
        $ins->execute([basename($file), $count]);

        return ['file'=>$file,'ok'=>true,'statements'=>$count,'error'=>null,'skipped'=>false];
    }

    /**
     * นำเข้าหลายไฟล์ตามลำดับ หยุดทันทีที่ไฟล์ใดพัง
     * @param array<string> $files
     * @return array<array{file:string,ok:bool,statements:int,error:?string,skipped:bool}>
     */
    public static function run(array $files): array
    {
        $res=[]; $stop=false;
        foreach($files as $f){
            if($stop){ $res[]=['file'=>$f,'ok'=>false,'statements'=>0,'error'=>null,'skipped'=>true]; continue; }
            $r=self::runFile($f);
            $res[]=$r;
            if(!$r['ok']) $stop=true;
        }
        return $res;
    }
}
