<?php
namespace App\Core;

/**
 * อ่านตารางจากไฟล์ .csv / .xlsx เป็น array ของแถว (แต่ละแถว = list ของค่าเซลล์)
 * ไม่ต้องพึ่งไลบรารีภายนอก (ใช้ ZipArchive + SimpleXML สำหรับ xlsx)
 */
final class SpreadsheetReader
{
    /** คืน array ของแถว (list ของ string) จากไฟล์ที่อัปโหลด */
    public static function rows(string $path, string $ext): array
    {
        $ext = strtolower($ext);
        if ($ext === 'csv' || $ext === 'txt') return self::csv($path);
        if ($ext === 'xlsx' || $ext === 'xlsm') return self::xlsx($path);
        return [];
    }

    private static function csv(string $path): array
    {
        $rows = [];
        if (($fh = fopen($path, 'r')) === false) return [];
        $first = true;
        while (($row = fgetcsv($fh)) !== false) {
            if ($first) {
                // ตัด BOM ออกจากเซลล์แรก
                if (isset($row[0])) $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$row[0]);
                $first = false;
            }
            // แปลงเป็น UTF-8 ถ้าไฟล์เป็น TIS-620/Windows-874
            $row = array_map(function ($v) {
                $v = (string)$v;
                if ($v !== '' && !mb_check_encoding($v, 'UTF-8')) {
                    $v = mb_convert_encoding($v, 'UTF-8', 'Windows-874,TIS-620,ISO-8859-11');
                }
                return trim($v);
            }, $row);
            $rows[] = $row;
        }
        fclose($fh);
        return $rows;
    }

    private static function xlsx(string $path): array
    {
        if (!class_exists('ZipArchive')) return [];
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) return [];

        // shared strings
        $shared = [];
        if (($ss = $zip->getFromName('xl/sharedStrings.xml')) !== false && $ss !== '') {
            $xml = @simplexml_load_string($ss);
            if ($xml !== false) {
                foreach ($xml->si as $si) {
                    $shared[] = self::siText($si);
                }
            }
        }

        // แผ่นงานแรก
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheet === false) {
            // เผื่อชื่อไม่ตรง — หาแผ่นแรกจากรายการ
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) { $sheet = $zip->getFromName($name); break; }
            }
        }
        $zip->close();
        if (!$sheet) return [];

        $xml = @simplexml_load_string($sheet);
        if ($xml === false || !isset($xml->sheetData)) return [];

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref  = (string)$c['r'];                       // เช่น "B3"
                $col  = self::colIndex(preg_replace('/\d+/', '', $ref)); // 0-based
                $type = (string)$c['t'];
                $val  = '';
                if ($type === 's') {                            // shared string
                    $idx = (int)$c->v;
                    $val = $shared[$idx] ?? '';
                } elseif ($type === 'inlineStr') {
                    $val = self::siText($c->is);
                } else {
                    $val = isset($c->v) ? (string)$c->v : '';
                }
                $cells[$col] = trim($val);
            }
            if (!$cells) { $rows[] = []; continue; }
            $max = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $max; $i++) $line[] = $cells[$i] ?? '';
            $rows[] = $line;
        }
        return $rows;
    }

    /** ข้อความรวมจาก <si> (รองรับ rich text <r><t>) */
    private static function siText(\SimpleXMLElement $si): string
    {
        if (isset($si->t)) return (string)$si->t;
        $t = '';
        if (isset($si->r)) foreach ($si->r as $r) $t .= (string)$r->t;
        return $t;
    }

    /** แปลงตัวอักษรคอลัมน์ (A, B, .., AA) เป็น index 0-based */
    private static function colIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $n = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }
        return max(0, $n - 1);
    }
}
