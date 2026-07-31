<?php
namespace App\Models;
use App\Core\Model;

/**
 * SAR — การประเมินมาตรฐานการศึกษาของสถานศึกษา ระดับปฐมวัย (11 มาตรฐาน / 100 คะแนน)
 *
 *  มฐ.1-4  ด้านคุณภาพผู้เรียน  (นับจำนวนเด็กที่ได้ระดับคุณภาพ 5-1 → ร้อยละที่ได้ระดับ 3 ขึ้นไป)
 *  มฐ.5    ครูปฏิบัติงาน...     (นับจำนวนครู — คิดแบบเดียวกับผู้เรียน)  → type 'quality'
 *  มฐ.6-11 ด้านการจัดการ/สังคม/อัตลักษณ์/ส่งเสริม (กรอกระดับที่ได้ 1-5 โดยตรง) → type 'level'
 *
 *  คะแนนรายมาตรฐาน = คะแนนเต็ม × Σ(น้ำหนัก×ระดับ) / Σ(น้ำหนัก×5)   (นอร์มัลไลซ์ให้เต็มตามคะแนนราชการ)
 *  ระดับคุณภาพ/แปลความหมาย เทียบจากร้อยละ (สิทธิ์ qa.kindergarten)
 */
class SarKindergarten extends Model
{
    /** ระดับคุณภาพ → คำแปล */
    public const MEANINGS = [5=>'ดีเยี่ยม', 4=>'ดีมาก', 3=>'ดี', 2=>'พอใช้', 1=>'ปรับปรุง'];

    /** เกณฑ์ร้อยละ → ระดับคุณภาพ (มาก→น้อย) */
    public const THRESHOLDS = [[80,5],[70,4],[60,3],[50,2],[0,1]];

    /** โครงสร้าง 11 มาตรฐาน (max=คะแนนเต็มราชการ, type, subject, ตัวบ่งชี้ [ชื่อ, น้ำหนัก]) */
    public const STANDARDS = [
        1 => ['group'=>'ด้านคุณภาพผู้เรียน', 'title'=>'เด็กมีพัฒนาการด้านร่างกาย', 'max'=>5, 'type'=>'quality', 'subject'=>'เด็ก', 'ind'=>[
            ['มีน้ำหนัก ส่วนสูงเป็นไปตามเกณฑ์มาตรฐาน', 1],
            ['มีทักษะในการเคลื่อนไหวตามวัย', 1.5],
            ['มีสุขนิสัยในการดูแลสุขภาพของตน', 1.5],
            ['หลีกเลี่ยงต่อสภาวะที่เสี่ยงต่อโรค อุบัติเหตุ ภัย และสิ่งเสพติด', 1],
        ]],
        2 => ['group'=>'ด้านคุณภาพผู้เรียน', 'title'=>'เด็กมีพัฒนาการด้านอารมณ์ และจิตใจ', 'max'=>5, 'type'=>'quality', 'subject'=>'เด็ก', 'ind'=>[
            ['ร่าเริงแจ่มใส มีความรู้สึกที่ดีต่อตนเอง', 1],
            ['มีความมั่นใจ และกล้าแสดงออก', 1],
            ['ควบคุมอารมณ์ตนเองได้เหมาะสมกับวัย', 1],
            ['ชื่นชมศิลปะ ดนตรี การเคลื่อนไหว และรักธรรมชาติ', 2],
        ]],
        3 => ['group'=>'ด้านคุณภาพผู้เรียน', 'title'=>'เด็กมีพัฒนาการด้านสังคม', 'max'=>5, 'type'=>'quality', 'subject'=>'เด็ก', 'ind'=>[
            ['มีวินัย รับผิดชอบ เชื่อฟังคำสั่งสอนของพ่อแม่ ครู อาจารย์', 2],
            ['มีความซื่อสัตย์สุจริต ช่วยเหลือแบ่งปัน', 1],
            ['เล่นและทำงานร่วมกับผู้อื่นได้', 1],
            ['ประพฤติปฏิบัติตนตามวัฒนธรรมไทย และศาสนาที่ตนนับถือ', 1],
        ]],
        4 => ['group'=>'ด้านคุณภาพผู้เรียน', 'title'=>'เด็กมีพัฒนาการด้านสติปัญญา', 'max'=>5, 'type'=>'quality', 'subject'=>'เด็ก', 'ind'=>[
            ['สนใจเรียนรู้สิ่งรอบตัว ซักถามอย่างตั้งใจ และรักการเรียนรู้', 1],
            ['มีความคิดรวบยอดเกี่ยวกับสิ่งต่าง ๆ ที่เกิดจากประสบการณ์การเรียนรู้', 1],
            ['มีทักษะทางภาษาที่เหมาะสมกับวัย', 1],
            ['มีทักษะกระบวนการทางวิทยาศาสตร์ และคณิตศาสตร์', 1],
            ['มีจินตนาการ และความคิดสร้างสรรค์', 1],
        ]],
        5 => ['group'=>'ด้านการจัดการศึกษา', 'title'=>'ครูปฏิบัติงานตามบทบาทหน้าที่อย่างมีประสิทธิภาพ และประสิทธิผล', 'max'=>20, 'type'=>'quality', 'subject'=>'ครู', 'ind'=>[
            ['ครูเข้าใจปรัชญา หลักการ และธรรมชาติของการจัดการศึกษาปฐมวัย และสามารถนำมาประยุกต์ใช้ในการจัดประสบการณ์', 2],
            ['ครูจัดทำแผนการจัดประสบการณ์ที่สอดคล้องกับหลักสูตรการศึกษาปฐมวัย และจัดประสบการณ์การเรียนรู้ที่หลากหลายสอดคล้องกับความแตกต่างระหว่างบุคคล', 2],
            ['ครูบริหารจัดการชั้นเรียนที่สร้างวินัยเชิงบวก', 2],
            ['ครูใช้สื่อและเทคโนโลยีเหมาะสมสอดคล้องกับพัฒนาการของเด็ก', 2],
            ['ครูใช้เครื่องมือวัดและประเมินพัฒนาการของเด็กอย่างหลากหลาย และสรุปรายงานผลพัฒนาการของเด็กแก่ผู้ปกครอง', 2],
            ['ครูวิจัยและพัฒนาการจัดการเรียนรู้ที่ตนรับผิดชอบ และใช้ผลในการปรับการจัดประสบการณ์', 2],
            ['ครูจัดสิ่งแวดล้อมให้เกิดการเรียนรู้ได้ตลอดเวลา', 2],
            ['ครูมีปฏิสัมพันธ์ที่ดีกับเด็ก และผู้ปกครอง', 2],
            ['ครูมีวุฒิ และความรู้ ความสามารถในด้านการศึกษาปฐมวัย', 2],
            ['ครูจัดทำสารนิเทศและนำมาไตร่ตรองเพื่อใช้ประโยชน์ในการพัฒนาเด็ก', 2],
        ]],
        6 => ['group'=>'ด้านการจัดการศึกษา', 'title'=>'ผู้บริหารปฏิบัติงานตามบทบาทหน้าที่อย่างมีประสิทธิภาพ และประสิทธิผล', 'max'=>20, 'type'=>'level', 'ind'=>[
            ['ผู้บริหารเข้าใจปรัชญา และหลักการของการจัดการศึกษาปฐมวัย', 3],
            ['ผู้บริหารมีวิสัยทัศน์ ภาวะผู้นำ และความคิดริเริ่มที่เน้นการพัฒนาเด็กปฐมวัย', 3],
            ['ผู้บริหารใช้หลักการบริหารแบบมีส่วนร่วมและใช้ข้อมูลการประเมินผลหรือการวิจัยเป็นฐานคิดทั้งด้านวิชาการและการจัดการ', 3],
            ['ผู้บริหารสามารถบริหารจัดการศึกษาให้บรรลุเป้าหมายตามแผนพัฒนาคุณภาพสถานศึกษา', 3],
            ['ผู้บริหารส่งเสริมและพัฒนาศักยภาพของบุคลากรให้มีประสิทธิภาพ', 3],
            ['ผู้บริหารให้คำปรึกษาทางวิชาการและเอาใจใส่การจัดการศึกษาปฐมวัยเต็มศักยภาพและเต็มเวลา', 3],
            ['เด็ก ผู้ปกครอง และชุมชนพึงพอใจผลการบริหารจัดการศึกษาปฐมวัย', 2],
        ]],
        7 => ['group'=>'ด้านการจัดการศึกษา', 'title'=>'แนวการจัดการศึกษา', 'max'=>20, 'type'=>'level', 'ind'=>[
            ['มีหลักสูตรการศึกษาปฐมวัยของสถานศึกษา และนำสู่การปฏิบัติได้อย่างมีประสิทธิภาพ', 4],
            ['มีระบบและกลไกให้ผู้มีส่วนร่วมทุกฝ่ายตระหนักและเข้าใจการจัดการศึกษาปฐมวัย', 4],
            ['จัดกิจกรรมเสริมสร้างความตระหนักรู้และเข้าใจหลักการจัดการศึกษาปฐมวัย', 4],
            ['สร้างการมีส่วนร่วมและแสวงหาความร่วมมือกับผู้ปกครอง ชุมชน และท้องถิ่น', 4],
            ['จัดสิ่งอำนวยความสะดวกเพื่อพัฒนาเด็กอย่างรอบด้าน', 4],
        ]],
        8 => ['group'=>'ด้านการจัดการศึกษา', 'title'=>'สถานศึกษามีการประกันคุณภาพภายในของสถานศึกษาตามที่กำหนดในกฎกระทรวง', 'max'=>5, 'type'=>'level', 'ind'=>[
            ['กำหนดมาตรฐานการศึกษาปฐมวัยของสถานศึกษา', 1],
            ['จัดทำและดำเนินการตามแผนพัฒนาการจัดการศึกษาของสถานศึกษาที่มุ่งคุณภาพตามมาตรฐานการศึกษาของสถานศึกษา', 1],
            ['จัดระบบข้อมูลสารสนเทศและใช้สารสนเทศในการบริหารจัดการ', 1],
            ['ติดตามตรวจสอบและประเมินคุณภาพภายในตามมาตรฐานการศึกษาของสถานศึกษา', 0.5],
            ['นำผลการประเมินคุณภาพทั้งภายในและภายนอกไปใช้วางแผนพัฒนาคุณภาพการศึกษาอย่างต่อเนื่อง', 0.5],
            ['จัดทำรายงานประจำปีที่เป็นรายงานการประเมินคุณภาพภายใน', 1],
        ]],
        9 => ['group'=>'ด้านการสร้างสังคมแห่งการเรียนรู้', 'title'=>'สถานศึกษามีการสร้าง ส่งเสริม สนับสนุนให้สถานศึกษาเป็นสังคมแห่งการเรียนรู้', 'max'=>5, 'type'=>'level', 'ind'=>[
            ['เป็นแหล่งเรียนรู้เพื่อพัฒนาการเรียนรู้ของเด็กและบุคลากรในสถานศึกษา', 2.5],
            ['มีการแลกเปลี่ยนเรียนรู้ระหว่างบุคลากรภายในสถานศึกษา ระหว่างสถานศึกษากับครอบครัว ชุมชนและองค์กรที่เกี่ยวข้อง', 2.5],
        ]],
        10 => ['group'=>'ด้านอัตลักษณ์ของสถานศึกษา', 'title'=>'การพัฒนาสถานศึกษาให้บรรลุเป้าหมายตามปรัชญา วิสัยทัศน์ และจุดเน้นของการศึกษาปฐมวัย', 'max'=>5, 'type'=>'level', 'ind'=>[
            ['จัดโครงการ กิจกรรมที่ส่งเสริมให้เด็กบรรลุตามเป้าหมาย ปรัชญา วิสัยทัศน์ และจุดเน้นการจัดการศึกษาปฐมวัยของสถานศึกษา', 3],
            ['ผลการดำเนินงานบรรลุเป้าหมาย ตามวิสัยทัศน์ ปรัชญา และจุดเน้นที่กำหนดขึ้น', 2],
        ]],
        11 => ['group'=>'ด้านมาตรการส่งเสริม', 'title'=>'การจัดกิจกรรมตามนโยบาย แนวทางการปฏิรูปการศึกษาเพื่อยกระดับคุณภาพให้สูงขึ้น', 'max'=>5, 'type'=>'level', 'ind'=>[
            ['จัดโครงการ กิจกรรมส่งเสริมสนับสนุนตามนโยบายเกี่ยวกับการจัดการศึกษาปฐมวัย', 3],
            ['ผลการดำเนินงานบรรลุเป้าหมาย', 2],
        ]],
    ];

    /** ร้อยละ → ระดับคุณภาพ (1-5) */
    public static function levelFromPercent(float $pct): int
    {
        foreach(self::THRESHOLDS as [$min,$lv]) if($pct >= $min) return $lv;
        return 1;
    }
    public static function meaning(int $level): string { return self::MEANINGS[$level] ?? '-'; }

    /** ตัวบ่งชี้ 1 รายการ (quality): ร้อยละที่ได้ระดับ 3 ขึ้นไป + ระดับ + คะแนนถ่วงน้ำหนัก */
    public static function indicatorQuality(array $row, float $weight): array
    {
        $total=(int)($row['total']??0);
        $n3plus=(int)($row['n5']??0)+(int)($row['n4']??0)+(int)($row['n3']??0);
        $pct = $total>0 ? round($n3plus/$total*100, 2) : 0.0;
        $level = $total>0 ? self::levelFromPercent($pct) : 0;
        return ['n3plus'=>$n3plus,'percent'=>$pct,'level'=>$level,'weight'=>$weight,'weighted'=>$weight*$level];
    }
    /** ตัวบ่งชี้ 1 รายการ (level): ใช้ระดับที่กรอกตรง ๆ */
    public static function indicatorLevel(array $row, float $weight): array
    {
        $level=(int)($row['lvl']??0);
        return ['level'=>$level,'weight'=>$weight,'weighted'=>$weight*$level];
    }

    /**
     * คำนวณทั้งมาตรฐาน → [indicators[], score, maxScore, percent, level, meaning]
     * score นอร์มัลไลซ์เป็นคะแนนเต็มราชการของมาตรฐานนั้น
     */
    public static function computeStandard(int $stdNo, array $scoresByInd): array
    {
        $def=self::STANDARDS[$stdNo];
        $inds=[]; $sumWeighted=0.0; $sumMax=0.0; $anyRated=false;
        foreach($def['ind'] as $i=>[$label,$w]){
            $row=$scoresByInd[$i+1] ?? [];
            $r = $def['type']==='quality'
                ? self::indicatorQuality($row,(float)$w)
                : self::indicatorLevel($row,(float)$w);
            $r['label']=$label; $r['row']=$row;
            $inds[$i+1]=$r;
            $sumMax += (float)$w * 5;
            $sumWeighted += $r['weighted'];
            if($r['level']>0) $anyRated=true;
        }
        $ratio = $sumMax>0 ? $sumWeighted/$sumMax : 0.0;
        $score = round($def['max'] * $ratio, 2);
        $percent = round($ratio*100, 2);
        $level = $anyRated ? self::levelFromPercent($percent) : 0;
        return [
            'indicators'=>$inds, 'score'=>$score, 'maxScore'=>(float)$def['max'],
            'percent'=>$percent, 'level'=>$level,
            'meaning'=>$level>0 ? self::meaning($level) : '-', 'rated'=>$anyRated,
        ];
    }

    /** คำนวณทั้งรายงาน → per-standard + สรุปรวม (คะแนนเต็ม 100) */
    public static function computeAll(array $rows): array
    {
        // จัดกลุ่ม rows → [std][ind] = row
        $byStd=[]; foreach($rows as $r){ $byStd[(int)$r['std_no']][(int)$r['ind_no']]=$r; }
        $standards=[]; $totalScore=0.0; $totalMax=0.0; $ratedCount=0;
        foreach(self::STANDARDS as $no=>$def){
            $c=self::computeStandard($no, $byStd[$no] ?? []);
            $standards[$no]=$c; $totalMax+=$c['maxScore'];
            if($c['rated']){ $totalScore+=$c['score']; $ratedCount++; }
        }
        $overallPct = $totalMax>0 ? round($totalScore/$totalMax*100, 2) : 0.0;
        $overallLevel = $ratedCount>0 ? self::levelFromPercent($overallPct) : 0;
        return [
            'standards'=>$standards,
            'totalScore'=>round($totalScore,2), 'totalMax'=>$totalMax,
            'overallPercent'=>$overallPct, 'overallLevel'=>$overallLevel,
            'overallMeaning'=>$overallLevel>0 ? self::meaning($overallLevel) : '-',
            'ratedStandards'=>$ratedCount,
        ];
    }

    // ---------- CRUD ----------
    public function years(): array
    {
        return $this->query("SELECT ay.id, ay.year_be, ay.is_current,
                (SELECT COUNT(*) FROM sar_kg s WHERE s.academic_year_id=ay.id) AS has_sar
            FROM academic_years ay ORDER BY ay.year_be DESC");
    }
    public function list(): array
    {
        return $this->query("SELECT s.*, ay.year_be
            FROM sar_kg s JOIN academic_years ay ON ay.id=s.academic_year_id
            ORDER BY ay.year_be DESC");
    }
    public function find(int $id): ?array
    {
        return $this->first("SELECT s.*, ay.year_be FROM sar_kg s
            JOIN academic_years ay ON ay.id=s.academic_year_id WHERE s.id=?", [$id]);
    }
    public function findByYear(int $yearId): ?array
    { return $this->first("SELECT * FROM sar_kg WHERE academic_year_id=?", [$yearId]); }

    public function create(int $yearId, ?int $by): int
    {
        $ex=$this->findByYear($yearId); if($ex) return (int)$ex['id'];
        $this->execute("INSERT INTO sar_kg (academic_year_id, created_by, updated_by) VALUES (?,?,?)",
            [$yearId,$by,$by]);
        return $this->lastId();
    }
    public function scores(int $sarId): array
    { return $this->query("SELECT * FROM sar_kg_scores WHERE sar_id=?", [$sarId]); }

    /** บันทึกคะแนนรายตัวบ่งชี้ (upsert) + summary_note */
    public function save(int $sarId, array $data, ?string $note, ?int $by): void
    {
        foreach(self::STANDARDS as $no=>$def){
            foreach($def['ind'] as $i=>$_){
                $ind=$i+1; $key="s{$no}_{$ind}";
                $d=$data[$key] ?? [];
                $this->execute(
                    "INSERT INTO sar_kg_scores (sar_id,std_no,ind_no,total,n5,n4,n3,n2,n1,lvl,evidence)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE total=VALUES(total),n5=VALUES(n5),n4=VALUES(n4),
                        n3=VALUES(n3),n2=VALUES(n2),n1=VALUES(n1),lvl=VALUES(lvl),evidence=VALUES(evidence)",
                    [$sarId,$no,$ind,
                     max(0,(int)($d['total']??0)), max(0,(int)($d['n5']??0)), max(0,(int)($d['n4']??0)),
                     max(0,(int)($d['n3']??0)), max(0,(int)($d['n2']??0)), max(0,(int)($d['n1']??0)),
                     min(5,max(0,(int)($d['lvl']??0))),
                     trim((string)($d['evidence']??''))?:null]);
            }
        }
        $this->execute("UPDATE sar_kg SET summary_note=?, updated_by=? WHERE id=?",
            [trim((string)$note)?:null, $by, $sarId]);
    }

    /** จำนวนเด็กปฐมวัยที่ active (ไว้เติมช่อง total อัตโนมัติ มฐ.1-4) */
    public function kgStudentCount(): int
    {
        return (int)($this->first("SELECT COUNT(*) c
            FROM student_enrollments se
            JOIN students s ON s.id=se.student_id AND s.deleted_at IS NULL
            JOIN classrooms c ON c.id=se.classroom_id
            JOIN grade_levels gl ON gl.id=c.grade_level_id AND gl.stage='kindergarten'
            WHERE se.status='active'")['c'] ?? 0);
    }
    /** จำนวนครูประจำชั้นอนุบาล (ไว้เติมช่อง total มฐ.5) */
    public function kgTeacherCount(): int
    {
        return (int)($this->first("SELECT COUNT(DISTINCT c.homeroom_teacher_id) c
            FROM classrooms c JOIN grade_levels gl ON gl.id=c.grade_level_id AND gl.stage='kindergarten'
            WHERE c.homeroom_teacher_id IS NOT NULL")['c'] ?? 0);
    }
}
