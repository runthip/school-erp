<?php
use App\Core\Auth;
use App\Core\Csrf;

/** escape HTML */
function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** สร้าง URL จาก base_url */
function base_url(string $path = ''): string
{
    $base = $GLOBALS['config']['app']['base_url'] ?? '';
    return $base . '/' . ltrim($path, '/');
}

/** URL แบบเต็ม (scheme+host+base) — ใช้ทำลิงก์ในอีเมล */
function absolute_url(string $path = ''): string
{
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
              || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . base_url($path);
}

/** asset URL */
function asset(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

/** CSRF field */
function csrf_field(): string
{
    return Csrf::field();
}

function csrf_token(): string
{
    return \App\Core\Csrf::token();
}

/** ตรวจสิทธิ์ */
function can(string $permission): bool
{
    return Auth::can($permission);
}

/** ผู้ใช้ปัจจุบัน */
function current_user(): ?array
{
    return Auth::user();
}

/** แปลงวันเวลาเป็นรูปแบบไทยอ่านง่าย */
function thai_datetime(?string $dt): string
{
    if (!$dt) return '-';
    $ts = strtotime($dt);
    return date('d/m/Y H:i', $ts);
}

/** class active สำหรับเมนู */
function nav_active(string $prefix): string
{
    $uri = App\Core\Request::uri();
    return str_starts_with($uri, $prefix) ? 'active' : '';
}

/** เมนูที่ผู้ใช้ปัจจุบันเห็นได้ (กรองตามสิทธิ์) */
function visible_menu(): array
{
    $menu = $GLOBALS['menu']['sections'] ?? [];
    $out = [];
    foreach ($menu as $section) {
        $items = array_filter($section['items'], function ($it) {
            if (!empty($it['perm']) && !Auth::can($it['perm'])) return false;
            // ซ่อนเมนูของช่วงชั้นที่ปิดใช้งาน (เช่น อนุบาล ถ้าโรงเรียนไม่มี)
            if (!empty($it['stage']) && !stage_enabled($it['stage'])) return false;
            return true;
        });
        if ($items) {
            $section['items'] = array_values($items);
            $out[] = $section;
        }
    }
    return $out;
}

/** ช่วงชั้นทั้งหมด → ชื่อไทย */
function all_stages(): array
{
    return ['kindergarten'=>'อนุบาล','primary'=>'ประถมศึกษา','lower_secondary'=>'มัธยมศึกษาตอนต้น','upper_secondary'=>'มัธยมศึกษาตอนปลาย'];
}

/** ช่วงชั้นที่โรงเรียนเปิดใช้งาน (แอดมินตั้งค่าได้) — ถ้ายังไม่ตั้ง ใช้ช่วงชั้นที่มีห้องเรียนอยู่ */
function enabled_stages(): array
{
    static $c=null;
    if($c!==null) return $c;
    $all=array_keys(all_stages());
    try {
        $pdo=\App\Core\Database::pdo();
        $rows=$pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE group_key='stages'")->fetchAll();
        if($rows){
            $c=[]; foreach($rows as $r){ if($r['setting_value']==='1' && in_array($r['setting_key'],$all,true)) $c[]=$r['setting_key']; }
            return $c = $c ?: $all;  // กันตั้งปิดหมด → คืนทั้งหมด
        }
        // ยังไม่เคยตั้งค่า → ช่วงชั้นที่มีห้องเรียนจริง
        $used=$pdo->query("SELECT DISTINCT gl.stage FROM classrooms cc JOIN grade_levels gl ON gl.id=cc.grade_level_id")->fetchAll(\PDO::FETCH_COLUMN);
        return $c = $used ? array_values(array_intersect($all,$used)) : $all;
    } catch (\Throwable $e) { return $c=$all; }
}

function stage_enabled(string $stage): bool { return in_array($stage, enabled_stages(), true); }

/** SQL IN(...) ของช่วงชั้นที่เปิดใช้งาน (ใช้กับคอลัมน์ stage) */
function enabled_stages_sql(string $col='gl.stage'): string
{
    $st=array_map(fn($s)=>preg_replace('/[^a-z_]/','',$s), enabled_stages());
    return $st ? "$col IN ('".implode("','",$st)."')" : '1=1';
}

/** ค้นหา menu item จาก url (เช่น 'm/grades') */
function menu_find_by_url(string $url): ?array
{
    foreach (($GLOBALS['menu']['sections'] ?? []) as $section) {
        foreach ($section['items'] as $it) {
            if ($it['url'] === $url) {
                $it['section'] = $section['title'];
                return $it;
            }
        }
    }
    return null;
}


function school_info(): array
{
    static $cache=null;
    if($cache!==null) return $cache;
    try {
        $st=\App\Core\Database::pdo()->query("SELECT name_th, province, affiliation FROM schools ORDER BY id LIMIT 1");
        $cache=$st->fetch() ?: [];
    } catch (\Throwable $e) { $cache=[]; }
    return $cache;
}

/** ตราสัญลักษณ์ระบบ (แถบเมนู/หน้าล็อกอิน): ชื่อ, คำบรรยาย, โลโก้ — ตั้งได้จากตั้งค่าระบบ */
function app_brand(): array
{
    static $c=null;
    if($c!==null) return $c;
    $out=['name'=>'School ERP','subtitle'=>'Enterprise','logo'=>''];
    try {
        $st=\App\Core\Database::pdo()->query("SELECT setting_key, setting_value FROM system_settings WHERE group_key='branding'");
        foreach(($st->fetchAll() ?: []) as $r){
            $k=$r['setting_key']; $v=(string)$r['setting_value'];
            if($k==='app_name' && trim($v)!=='') $out['name']=$v;
            elseif($k==='app_subtitle') $out['subtitle']=$v;
            elseif($k==='app_logo') $out['logo']=$v;
        }
    } catch (\Throwable $e) {}
    return $c=$out;
}

/** การแจ้งเตือนของผู้ใช้ปัจจุบัน สำหรับกระดิ่งบนแถบหัว (แคชต่อ request) */
function user_notifications(): array
{
    static $c=null;
    if($c!==null) return $c;
    $c=['count'=>0,'items'=>[]];
    $uid=\App\Core\Auth::id();
    if(!$uid) return $c;
    try {
        $pdo=\App\Core\Database::pdo();
        $st=$pdo->prepare("SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0");
        $st->execute([$uid]); $c['count']=(int)($st->fetch()['c'] ?? 0);
        $st=$pdo->prepare("SELECT id, title, body, link, is_read, created_at FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 6");
        $st->execute([$uid]); $c['items']=$st->fetchAll() ?: [];
    } catch (\Throwable $e) {}
    return $c;
}

/** โลโก้โรงเรียน + ตราครุฑ ที่อัปโหลดไว้ (แคชต่อ request) */
function brand_assets(): array
{
    static $c=null;
    if($c!==null) return $c;
    $c=['logo'=>null,'garuda'=>null,'show_garuda'=>true];
    try {
        $pdo=\App\Core\Database::pdo();
        $s=$pdo->query("SELECT logo_path FROM schools ORDER BY id LIMIT 1")->fetch();
        $c['logo']=!empty($s['logo_path']) ? $s['logo_path'] : null;
        $rows=$pdo->query("SELECT setting_key, setting_value FROM system_settings
            WHERE group_key='document' AND setting_key IN ('garuda_path','show_garuda')")->fetchAll();
        foreach($rows as $r){
            if($r['setting_key']==='garuda_path') $c['garuda']=$r['setting_value']?:null;
            if($r['setting_key']==='show_garuda') $c['show_garuda']=$r['setting_value']!=='0';
        }
    } catch (\Throwable $e) {}
    return $c;
}

/** แท็กรูปลายเซ็น (คืนค่าว่างถ้าไม่มีลายเซ็น) — ใช้วางเหนือชื่อ-ตำแหน่งบนเอกสาร */
function sign_img(?string $path, int $h=46): string
{
    if (empty($path)) return '';
    return '<img src="'.e(base_url($path)).'" alt="ลายเซ็น" style="height:'.$h.'px;width:auto;max-width:220px;object-fit:contain;display:block;margin:0 auto 2px">';
}

/**
 * บล็อกลงนามมาตรฐาน: [ลายเซ็น หรือ เส้นประ] + (ชื่อ) + ตำแหน่ง
 * $label เช่น 'ผู้ยืม', 'เจ้าหน้าที่การเงิน' (ต่อท้ายบรรทัด "ลงชื่อ")
 */
function sign_block(?string $sigPath, string $name='', string $position='', string $label='', bool $center=true): string
{
    $h = '<div style="'.($center?'text-align:center;':'').'margin-top:10px">';
    if (!empty($sigPath)) {
        $h .= sign_img($sigPath);
        $h .= '<div style="font-size:13px">ลงชื่อ '.($label!==''?e($label):'').'</div>';
    } else {
        $h .= '<div style="font-size:13px">ลงชื่อ ................................................'.($label!==''?' '.e($label):'').'</div>';
    }
    $h .= '<div style="font-size:13px">( '.e($name!==''?$name:'.......................................').' )</div>';
    if ($position!=='') $h .= '<div style="font-size:12.5px">'.e($position).'</div>';
    $h .= '</div>';
    return $h;
}

/** ลายเซ็นของบุคลากร (แมป personnel → users.signature_path) */
function personnel_signature(?int $personnelId): ?string
{
    if (!$personnelId) return null;
    static $cache=[];
    if (array_key_exists($personnelId,$cache)) return $cache[$personnelId];
    try {
        $st=\App\Core\Database::pdo()->prepare("SELECT signature_path FROM users
            WHERE linked_type='personnel' AND linked_id=? AND deleted_at IS NULL AND signature_path IS NOT NULL LIMIT 1");
        $st->execute([$personnelId]);
        $r=$st->fetch();
        return $cache[$personnelId] = ($r && !empty($r['signature_path'])) ? $r['signature_path'] : null;
    } catch (\Throwable $e) { return $cache[$personnelId]=null; }
}

/** ตราครุฑ: รูปที่อัปโหลด ไม่งั้นใช้สัญลักษณ์ ◆ แทน (ใช้กับเอกสารราชการทั่วไป) */
function garuda_mark(int $h=64): string
{
    $b=brand_assets();
    if(!empty($b['garuda'])) return '<img src="'.e(base_url($b['garuda'])).'" alt="ตราครุฑ" style="height:'.$h.'px;width:auto;object-fit:contain;display:block;margin:0 auto">';
    return '<div class="doc-garuda" style="font-size:'.max(22,(int)($h*0.42)).'px;color:#7a5c1e;line-height:1;text-align:center">◆</div>';
}

/** ตราโรงเรียน (โลโก้): ใช้เฉพาะเอกสาร ปพ. — คืนค่าว่างถ้ายังไม่อัปโหลด */
function logo_mark(int $h=64): string
{
    $b=brand_assets();
    if(!empty($b['logo'])) return '<img src="'.e(base_url($b['logo'])).'" alt="ตราโรงเรียน" style="height:'.$h.'px;width:auto;object-fit:contain;display:block;margin:0 auto">';
    return '';
}

/**
 * หัวเอกสารราชการมาตรฐาน
 * @param string $emblem 'garuda' (ค่าเริ่มต้น — ตราครุฑ) หรือ 'logo' (ตราโรงเรียน เช่น ปพ.5)
 */
function doc_header(string $title, string $subtitle='', string $emblem='garuda'): string
{
    $sc=school_info();
    $b=brand_assets();
    $name=e($sc['name_th'] ?? 'โรงเรียน');
    $aff=trim(($sc['affiliation'] ?? '').' '.($sc['province'] ?? ''));
    $h='<div class="doc-head">';
    if($emblem==='logo'){
        // เอกสาร ปพ. → ใช้ตราโรงเรียน (ถ้ายังไม่อัปโหลด ค่อยใช้ครุฑแทนชั่วคราว)
        $mk=logo_mark(64); $h.= $mk!=='' ? $mk : garuda_mark(64);
    } elseif($b['show_garuda']){
        $h.=garuda_mark(64);
    }
    $h.='<div class="doc-school">'.$name.'</div>';
    if($aff!=='') $h.='<div class="doc-aff">สังกัด'.e($aff).'</div>';
    $h.='<div class="doc-title">'.e($title).'</div>';
    if($subtitle!=='') $h.='<div class="doc-sub">'.e($subtitle).'</div>';
    $h.='</div>';
    return $h;
}

if (!function_exists('baht_text')) {
    /** แปลงจำนวนเงินเป็นข้อความภาษาไทย เช่น 1250.50 → หนึ่งพันสองร้อยห้าสิบบาทห้าสิบสตางค์ */
    function baht_text($number): string
    {
        $number = (float)$number;
        $txtNum = ['ศูนย์','หนึ่ง','สอง','สาม','สี่','ห้า','หก','เจ็ด','แปด','เก้า'];
        $txtPos = ['','สิบ','ร้อย','พัน','หมื่น','แสน','ล้าน'];
        $readInt = function(string $n) use ($txtNum, $txtPos): string {
            $len = strlen($n); $out = '';
            for ($i = 0; $i < $len; $i++) {
                $d = (int)$n[$i]; $pos = $len - $i - 1; $posInMil = $pos % 6;
                if ($d !== 0) {
                    if ($posInMil === 1 && $d === 1) $out .= 'สิบ';
                    elseif ($posInMil === 1 && $d === 2) $out .= 'ยี่สิบ';
                    elseif ($posInMil === 0 && $d === 1 && $len > 1 && ($i === 0 ? false : ((int)$n[$i-1] !== 0 || $len - $i === 1)) ) $out .= 'เอ็ด';
                    elseif ($posInMil === 0 && $d === 1 && $i > 0) $out .= 'เอ็ด';
                    else $out .= $txtNum[$d] . $txtPos[$posInMil];
                }
                if ($pos > 0 && $pos % 6 === 0) $out .= 'ล้าน';
            }
            return $out;
        };
        $number = round($number, 2);
        $baht = (int)floor($number);
        $satang = (int)round(($number - $baht) * 100);
        $result = '';
        if ($baht === 0) $result = 'ศูนย์บาท';
        else $result = $readInt((string)$baht) . 'บาท';
        if ($satang === 0) $result .= 'ถ้วน';
        else $result .= $readInt((string)$satang) . 'สตางค์';
        return $result;
    }
}

/** แปลงค่าลิมิตแบบ php.ini ("2M", "8M", "512K") เป็นจำนวนไบต์ */
function ini_bytes(string $v): int
{
    $v = trim($v);
    if ($v === '' || $v === '-1') return 0;                 // 0 = ไม่จำกัด
    $unit = strtolower($v[strlen($v)-1]);
    $num  = (int)$v;
    return match($unit) {
        'g' => $num * 1024 * 1024 * 1024,
        'm' => $num * 1024 * 1024,
        'k' => $num * 1024,
        default => (int)$v,
    };
}

/** แสดงขนาดไบต์ให้อ่านง่าย */
function human_bytes(int $b): string
{
    if ($b <= 0) return 'ไม่จำกัด';
    if ($b >= 1073741824) return round($b/1073741824,1).' GB';
    if ($b >= 1048576)    return round($b/1048576,1).' MB';
    if ($b >= 1024)       return round($b/1024).' KB';
    return $b.' B';
}

/**
 * ลิมิตการอัปโหลดที่ "มีผลจริง" บนเซิร์ฟเวอร์นี้
 * โค้ดจะกำหนดเพดานเองเท่าไรก็ตาม PHP ตัดที่ค่าเหล่านี้ก่อนเสมอ
 * @return array{per_file:int,total:int,max_files:int,enabled:bool}
 */
function upload_limits(int $appCap = 0): array
{
    $u = ini_bytes((string)ini_get('upload_max_filesize'));
    $p = ini_bytes((string)ini_get('post_max_size'));
    $perFile = $u;
    if ($appCap > 0 && ($perFile === 0 || $appCap < $perFile)) $perFile = $appCap;
    return [
        'per_file'  => $perFile,
        'total'     => $p,
        'max_files' => (int)ini_get('max_file_uploads'),
        'enabled'   => (bool)ini_get('file_uploads'),
    ];
}

/**
 * จำนวนหนังสือที่ส่งถึงผู้ใช้ปัจจุบันและยังไม่เปิดอ่าน (ใช้ทำ badge บนเมนู)
 * ต้องไม่พังเมื่อยังไม่ได้นำเข้า SQL งานสารบรรณ จึงกลืน error แล้วคืน 0
 */
function doc_inbox_unread(): int
{
    static $n = null;
    if ($n !== null) return $n;
    $n = 0;
    try {
        $u = \App\Core\Auth::user();
        if (!$u || ($u['linked_type'] ?? '') !== 'personnel' || empty($u['linked_id'])) return $n = 0;
        $st = \App\Core\Database::pdo()->prepare(
            "SELECT COUNT(*) FROM document_recipients
              WHERE recipient_personnel_id = ? AND status='pending' AND is_read = 0");
        $st->execute([(int)$u['linked_id']]);
        return $n = (int)$st->fetchColumn();
    } catch (\Throwable $e) { return $n = 0; }
}

/** ค่าที่ใช้ทำ badge บนเมนู — อ้างด้วยชื่อจาก config/menu.php */
function menu_badge(string $key): int
{
    return match($key) {
        'inbox' => doc_inbox_unread(),
        default => 0,
    };
}
