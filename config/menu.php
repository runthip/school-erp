<?php
/**
 * โครงสร้างเมนู + แคตตาล็อกสิทธิ์ + การจับคู่บทบาท↔สิทธิ์
 * ใช้เป็นแหล่งความจริงเดียว: สร้างสิทธิ์, เรนเดอร์ sidebar, และหน้าโมดูล
 *
 * แต่ละ item: [label, icon, url, perm, module]
 *   - perm = null  → เห็นได้ทุกคนที่ล็อกอิน
 *   - url ที่ขึ้นต้นด้วย 'm/' = หน้าโมดูล placeholder (อยู่ระหว่างพัฒนา)
 */

return [

    // ---------- เมนู (จัดกลุ่มตามฝ่าย) ----------
    'sections' => [
        ['title' => 'ทั่วไป', 'items' => [
            ['label'=>'แดชบอร์ด','icon'=>'▦','url'=>'dashboard','perm'=>null],
            ['label'=>'ปฏิทินวิชาการ','icon'=>'📅','url'=>'calendar','perm'=>null],
            // ── งานสารบรรณอิเล็กทรอนิกส์ (แจ้งเตือนหนังสือเข้า — เข้าถึงเร็วจากหน้าหลัก) ──
            ['label'=>'หนังสือถึงฉัน','icon'=>'📥','url'=>'documents/inbox','perm'=>'document.inbox','badge'=>'inbox'],
            ['label'=>'รับ-ส่งหนังสือ','icon'=>'✉','url'=>'documents','perm'=>'document.manage'],
            ['label'=>'หนังสือรอสั่งการ/ลงนาม','icon'=>'✍','url'=>'documents?director=pending&type=incoming','perm'=>'document.sign'],
            // ── เช็คชื่อ/การมาเรียน ──
            ['label'=>'เช็คชื่อหน้าเสาธง','icon'=>'🏫','url'=>'attendance/flag','perm'=>'academic.attendance'],
            ['label'=>'รายงานการมาเรียน','icon'=>'📊','url'=>'attendance/report','perm'=>'academic.attendance_report'],
        ]],
        ['title' => 'ฝ่ายบริหาร', 'items' => [
            ['label'=>'Dashboard ผู้บริหาร','icon'=>'📊','url'=>'exec/dashboard','perm'=>'admin.dashboard'],
            ['label'=>'KPI โรงเรียน','icon'=>'◎','url'=>'kpi','perm'=>'admin.dashboard'],
            ['label'=>'อนุมัติเอกสารออนไลน์','icon'=>'✓','url'=>'approvals','perm'=>'admin.approve'],
            ['label'=>'E-Office · คลังแบบฟอร์ม','icon'=>'▤','url'=>'eoffice','perm'=>'admin.eoffice'],
            ['label'=>'ติดตามโครงการ','icon'=>'◷','url'=>'projects','perm'=>'admin.projects'],
            ['label'=>'รายงานผลการดำเนินงาน','icon'=>'▤','url'=>'reports','perm'=>'admin.reports'],
            ['label'=>'รายงานผลกิจกรรม/การแข่งขัน','icon'=>'🏆','url'=>'activities','perm'=>'activity.report'],
            ['label'=>'SAR มาตรฐานการศึกษา (ปฐมวัย)','icon'=>'🧸','url'=>'sar-kg','perm'=>'qa.kindergarten','stage'=>'kindergarten'],
        ]],
        // ── ฝ่ายวิชาการ (1) การจัดการ — แอดมิน/หัวหน้าฝ่ายวิชาการเท่านั้น (เพิ่ม/แก้ไข/ลบข้อมูลหลัก) ──
        ['title' => 'วิชาการ · การจัดการ (แอดมิน)', 'items' => [
            ['label'=>'จัดการชั้นเรียน','icon'=>'🏫','url'=>'classes','perm'=>'academic.curriculum'],
            ['label'=>'ข้อมูลนักเรียน','icon'=>'🎓','url'=>'students','perm'=>'student.profile'],
            ['label'=>'จัดการนักเรียน (แยกห้อง)','icon'=>'🧩','url'=>'academic/students','perm'=>'academic.curriculum'],
            ['label'=>'จัดการครูผู้สอน','icon'=>'🧑‍🏫','url'=>'teachers','perm'=>'academic.curriculum'],
            ['label'=>'หลักสูตร (รายวิชา)','icon'=>'❑','url'=>'subjects','perm'=>'academic.curriculum'],
            ['label'=>'มอบหมายวิชาสอน','icon'=>'👨‍🏫','url'=>'teaching','perm'=>'academic.curriculum'],
            ['label'=>'ตั้งค่าเงื่อนไขจัดตาราง','icon'=>'⚙','url'=>'schedule/settings','perm'=>'schedule.manage'],
            ['label'=>'จัดตารางสอน (AI)','icon'=>'✨','url'=>'schedule/build','perm'=>'schedule.manage'],
            ['label'=>'แก้ไขผลการเรียน 0 ร มส','icon'=>'🛠','url'=>'zero-rms','perm'=>'academic.curriculum'],
            ['label'=>'ติดตามการกรอกคะแนน','icon'=>'◍','url'=>'grade-monitor','perm'=>'academic.monitor'],
        ]],
        // ── ฝ่ายวิชาการ (2) การใช้งานของครู/ผู้บริหาร — งานประจำวัน + มุมมองรายงาน (ดู/พิมพ์) ──
        ['title' => 'วิชาการ · ครู/ผู้บริหาร (ใช้งาน-ดู-พิมพ์)', 'items' => [
            ['label'=>'ขั้นตอนงานวิชาการ','icon'=>'🧭','url'=>'workflow','perm'=>'academic.schedule'],
            // ── งานประจำวันของครู (บันทึกได้) ──
            ['label'=>'เช็คชื่อ','icon'=>'☑','url'=>'attendance','perm'=>'academic.attendance'],
            ['label'=>'บันทึกคะแนน + ประเมิน','icon'=>'✎','url'=>'scores','perm'=>'academic.grades'],
            ['label'=>'ประเมินพัฒนาการอนุบาล','icon'=>'🧸','url'=>'kindergarten','perm'=>'academic.grades','stage'=>'kindergarten'],
            ['label'=>'บันทึกหลังการสอน','icon'=>'📔','url'=>'teaching-log','perm'=>'academic.grades'],
            ['label'=>'งานวัดผล / ข้อสอบ','icon'=>'📝','url'=>'exams','perm'=>'academic.measurement'],
            ['label'=>'จัดสอนแทน (วันครูลา)','icon'=>'🔁','url'=>'substitute','perm'=>'academic.schedule'],
            // ── มุมมอง/รายงาน (ดูและพิมพ์เท่านั้น) ──
            ['label'=>'ตารางเรียน/ตารางสอน','icon'=>'▦','url'=>'schedule','perm'=>'academic.schedule'],
            ['label'=>'Dashboard ตารางสอน','icon'=>'▦','url'=>'schedule/dashboard','perm'=>'academic.schedule'],
            ['label'=>'สรุปยอดนักเรียน','icon'=>'📊','url'=>'headcount','perm'=>'student.headcount'],
            ['label'=>'ผลการเรียน (GPA)','icon'=>'▤','url'=>'grades','perm'=>'academic.grades'],
            ['label'=>'นักเรียนติด 0 ร มส (ดู/พิมพ์)','icon'=>'⚠','url'=>'zero-rms','perm'=>'academic.grades'],
            ['label'=>'เอกสาร ปพ. / Transcript','icon'=>'▤','url'=>'pp-transcript','perm'=>'academic.pp'],
        ]],
        ['title' => 'ฝ่ายงบประมาณ/พัสดุ', 'items' => [
            // ── ศูนย์รวม flow ทั้งฝ่าย ──
            ['label'=>'ศูนย์ฝ่ายงบประมาณ/พัสดุ','icon'=>'🏦','url'=>'budget-hub','perm'=>'budget.memo'],
            // ── ขั้นที่ 1-2: วางแผน · โครงการ/กิจกรรม ──
            ['label'=>'แผนงาน/โครงการ','icon'=>'📋','url'=>'planning','perm'=>'budget.planning'],
            ['label'=>'ติดตามโครงการ','icon'=>'◷','url'=>'projects','perm'=>'admin.projects'],
            // ── ขั้นที่ 3: บันทึกขอเบิก (งป.01 = ทางเดียว) ──
            ['label'=>'บันทึกขอเบิก (งป.01)','icon'=>'📝','url'=>'budget-memo','perm'=>'budget.memo'],
            ['label'=>'รายงานงบประมาณ งป.01','icon'=>'💰','url'=>'budget-memo/report','perm'=>'budget.memo'],
            // ── ขั้นที่ 4: พัสดุ/จัดซื้อจัดจ้าง ──
            ['label'=>'ขอซื้อ/ขอจ้าง','icon'=>'🛒','url'=>'purchase','perm'=>'budget.purchase'],
            ['label'=>'ใบสั่งซื้อ (PO)/สัญญา','icon'=>'▤','url'=>'po','perm'=>'budget.po'],
            ['label'=>'ครุภัณฑ์/พัสดุ (จัดการ)','icon'=>'▣','url'=>'general/assets','perm'=>'inventory.manage'],
            // ── ขั้นที่ 5: จ่าย · บัญชีคุมงบ · รายงาน ──
            ['label'=>'ยืมเงินราชการ','icon'=>'🧾','url'=>'planning/advances','perm'=>'budget.planning'],
            ['label'=>'เบิกค่าเดินทางไปราชการ (8708)','icon'=>'🧾','url'=>'travel','perm'=>'travel.claim'],
            ['label'=>'บันทึกขอคืนเงินยืม','icon'=>'↩','url'=>'refund-memo','perm'=>'budget.refund_memo'],
            ['label'=>'บัญชีคุมงบประมาณ','icon'=>'⚖','url'=>'planning/ledger','perm'=>'budget.ledger'],
            ['label'=>'รายงานผู้บริหาร (Real-time)','icon'=>'📊','url'=>'planning/exec','perm'=>'budget.report'],
            ['label'=>'รายงานงบ','icon'=>'📈','url'=>'reports/budget','perm'=>'budget.report'],
        ]],
        ['title' => 'ฝ่ายบุคคล', 'items' => [
            ['label'=>'ภาพรวมงานบุคคล','icon'=>'📇','url'=>'hr','perm'=>'hr.personnel'],
            ['label'=>'ข้อมูลบุคลากร','icon'=>'👤','url'=>'personnel','perm'=>'hr.personnel'],
            ['label'=>'การลา/มาสาย','icon'=>'⏱','url'=>'hr/leaves','perm'=>'hr.leave'],
            ['label'=>'ลงเวลาปฏิบัติงาน','icon'=>'☝','url'=>'hr/attendance','perm'=>'hr.attendance'],
            ['label'=>'เงินเดือน','icon'=>'฿','url'=>'hr/salary','perm'=>'hr.salary'],
            ['label'=>'PA ของฉัน (หัวข้อ+ไฟล์)','icon'=>'📌','url'=>'pa','perm'=>'pa.own'],
            ['label'=>'PA / วิทยฐานะ / ประเมิน','icon'=>'★','url'=>'hr/pa','perm'=>'hr.evaluation'],
            ['label'=>'รายงานประเมินตนเอง (SAR)','icon'=>'📄','url'=>'sar','perm'=>'sar.own'],
            ['label'=>'ภาพรวม SAR','icon'=>'📈','url'=>'sar/report','perm'=>'sar.report'],
        ]],
        ['title' => 'ฝ่ายบริหารทั่วไป', 'items' => [
            ['label'=>'ภาพรวมบริหารทั่วไป','icon'=>'🗂','url'=>'general','perm'=>'general.repair'],
            ['label'=>'แจ้งซ่อม/อาคารสถานที่','icon'=>'🔧','url'=>'general/repairs','perm'=>'general.repair'],
            ['label'=>'พัสดุ/ครุภัณฑ์ (ดู/แก้สถานที่)','icon'=>'📦','url'=>'general/assets','perm'=>'general.asset'],
            ['label'=>'รายงานครุภัณฑ์','icon'=>'📈','url'=>'general/assets/report','perm'=>'general.asset'],
            ['label'=>'ร้านค้าสวัสดิการ','icon'=>'🏪','url'=>'general/welfare','perm'=>'general.welfare'],
            ['label'=>'งานพยาบาล / ห้องพยาบาล','icon'=>'⚕','url'=>'general/nurse','perm'=>'general.health'],
            ['label'=>'คลังยา / เวชภัณฑ์','icon'=>'💊','url'=>'general/medicines','perm'=>'general.health'],
            ['label'=>'ยานพาหนะ','icon'=>'🚐','url'=>'general/vehicles','perm'=>'general.vehicle'],
            ['label'=>'ขอใช้รถ','icon'=>'🗓','url'=>'general/bookings','perm'=>'general.vehicle'],
        ]],
        ['title' => 'ฝ่ายกิจการนักเรียน', 'items' => [
            ['label'=>'สุขภาพ (BMI/น้ำหนัก/ส่วนสูง)','icon'=>'⚕','url'=>'health','perm'=>'student.health'],
            ['label'=>'ภาพรวมกิจการนักเรียน','icon'=>'◈','url'=>'affairs','perm'=>'student.behavior'],
            ['label'=>'พฤติกรรมนักเรียน','icon'=>'♥','url'=>'affairs/behaviors','perm'=>'student.behavior'],
            ['label'=>'ประเมิน SDQ','icon'=>'◎','url'=>'affairs/sdq','perm'=>'student.behavior'],
            ['label'=>'ระบบดูแลช่วยเหลือ/เยี่ยมบ้าน','icon'=>'🏠','url'=>'affairs/visits','perm'=>'student.care'],
            ['label'=>'ทุนการศึกษา','icon'=>'✦','url'=>'affairs/scholarships','perm'=>'student.scholarship'],
        ]],
        ['title' => 'พอร์ทัลของฉัน', 'items' => [
            ['label'=>'พอร์ทัลของฉัน','icon'=>'◈','url'=>'my','perm'=>'portal.student'],
            ['label'=>'ผลการเรียนของฉัน','icon'=>'▤','url'=>'my/grades','perm'=>'portal.student'],
            ['label'=>'ห้องเรียนของฉัน','icon'=>'▶','url'=>'my/classroom','perm'=>'portal.student'],
            ['label'=>'การมาเรียนของฉัน','icon'=>'✓','url'=>'my/attendance','perm'=>'portal.student'],
            ['label'=>'ความประพฤติของฉัน','icon'=>'♥','url'=>'my/behavior','perm'=>'portal.student'],
            ['label'=>'ติดตามบุตรหลาน','icon'=>'👨‍👩‍👧','url'=>'my-children','perm'=>'portal.guardian'],
            ['label'=>'แจ้งลา','icon'=>'✉','url'=>'m/my-leave','perm'=>'portal.guardian'],
        ]],
        ['title' => 'ความปลอดภัย', 'items' => [
            ['label'=>'ผู้ใช้งาน','icon'=>'◉','url'=>'users','perm'=>'user.manage'],
            ['label'=>'บทบาทและสิทธิ์','icon'=>'⚿','url'=>'roles','perm'=>'role.manage'],
            ['label'=>'สิทธิ์ทั้งหมด','icon'=>'☰','url'=>'permissions','perm'=>'role.manage'],
            ['label'=>'ประวัติการใช้งาน','icon'=>'⟲','url'=>'audit-logs','perm'=>'audit.view'],
            ['label'=>'นำเข้าฐานข้อมูล','icon'=>'⬇','url'=>'setup/migrations','perm'=>'system.settings'],
            ['label'=>'จัดการฐานข้อมูลสารบรรณ','icon'=>'♻','url'=>'setup/doc-reset','perm'=>'system.settings'],
            ['label'=>'สำรองข้อมูล','icon'=>'💾','url'=>'setup/backup','perm'=>'system.settings'],
            ['label'=>'ตั้งค่าระบบ','icon'=>'⚙','url'=>'settings','perm'=>'system.settings'],
        ]],
    ],

    // ---------- ชื่อสิทธิ์ (code → ชื่อไทย) ----------
    'permission_names' => [
        'admin.dashboard'=>'ดู Dashboard ผู้บริหาร', 'admin.approve'=>'อนุมัติเอกสาร',
        'admin.eoffice'=>'E-Office/หนังสือราชการ', 'admin.projects'=>'ติดตามโครงการ', 'admin.reports'=>'รายงานภาพรวม',
        'qa.kindergarten'=>'SAR มาตรฐานการศึกษา ปฐมวัย',
        'academic.curriculum'=>'จัดการหลักสูตร', 'academic.schedule'=>'ตารางเรียน/สอน', 'schedule.manage'=>'จัดตารางสอน (แอดมิน/หัวหน้าวิชาการ)',
        'academic.grades'=>'บันทึกคะแนน/ผลการเรียน', 'academic.attendance'=>'เช็คชื่อนักเรียน', 'academic.attendance_report'=>'รายงานการมาเรียน',
        'academic.measurement'=>'งานวัดผล/ข้อสอบ', 'academic.monitor'=>'ติดตามการกรอกคะแนน', 'academic.pp'=>'เอกสาร ปพ./Transcript',
        'budget.manage'=>'จัดการงบประมาณ', 'budget.purchase'=>'ขอซื้อ/ขอจ้าง', 'budget.po'=>'ใบสั่งซื้อ/สัญญา',
        'budget.report'=>'รายงานงบประมาณ', 'budget.planning'=>'งานแผน/โครงการ/เบิกจ่าย', 'budget.approve'=>'อนุมัติขอซื้อ/ขอจ้าง', 'budget.refund_memo'=>'บันทึกขอคืนเงินยืม', 'inventory.manage'=>'จัดการครุภัณฑ์/พัสดุ',
        'hr.personnel'=>'ข้อมูลบุคลากร', 'hr.leave'=>'จัดการการลา', 'hr.attendance'=>'ลงเวลาปฏิบัติงาน',
        'hr.salary'=>'เงินเดือน', 'hr.evaluation'=>'PA/วิทยฐานะ/ประเมิน', 'pa.own'=>'จัดการ PA ของตนเอง',
        'general.repair'=>'แจ้งซ่อม/อาคาร', 'general.booking'=>'จองห้อง', 'general.vehicle'=>'ยานพาหนะ',
        'general.health'=>'งานอนามัย/ห้องพยาบาล', 'general.welfare'=>'ร้านค้าสวัสดิการ', 'general.pr'=>'ผู้มาติดต่อ/ประชาสัมพันธ์',
        'student.profile'=>'ข้อมูลนักเรียน', 'student.behavior'=>'พฤติกรรม/SDQ',
        'student.care'=>'ระบบดูแลช่วยเหลือ', 'student.scholarship'=>'ทุนการศึกษา',
        'portal.student'=>'พอร์ทัลนักเรียน', 'portal.guardian'=>'พอร์ทัลผู้ปกครอง',
        'document.manage'=>'สารบรรณ รับ-ส่ง', 'document.sign'=>'ลงนามดิจิทัล/เวียน',
        'user.manage'=>'จัดการผู้ใช้', 'role.manage'=>'จัดการบทบาท/สิทธิ์',
        'audit.view'=>'ดูประวัติการใช้งาน', 'system.settings'=>'ตั้งค่าระบบ', 'system.backup'=>'สำรอง/กู้คืนข้อมูล',
    ],

    // ---------- บทบาท → สิทธิ์ ( '*' = ทุกสิทธิ์ ) ----------
    'role_permissions' => [
        'super_admin'    => ['*'],
        'director'       => ['budget.ledger','general.asset','general.document','budget.planning','admin.dashboard','admin.approve','admin.eoffice','admin.projects','admin.reports',
                             'academic.pp','budget.report','hr.evaluation','audit.view','academic.monitor','budget.approve','document.manage','document.sign','academic.attendance','academic.attendance_report','sar.own','sar.review','sar.report','budget.memo','budget.memo_approve','budget.purchase','budget.po','qa.kindergarten'],
        'deputy_director'=> ['budget.ledger','general.asset','general.document','budget.planning','admin.dashboard','admin.approve','admin.projects','admin.reports','academic.schedule','budget.report','academic.monitor','budget.approve','document.manage','document.sign','academic.attendance','academic.attendance_report','sar.own','sar.review','sar.report','budget.memo','budget.memo_approve','budget.purchase','budget.po'],
        'head_academic'  => ['academic.curriculum','academic.schedule','schedule.manage','academic.grades','academic.attendance',
                             'academic.measurement','academic.pp','admin.approve','academic.monitor','academic.attendance','academic.attendance_report','sar.own','sar.review','sar.report','budget.memo'],
        'head_budget'    => ['budget.ledger','budget.planning','budget.manage','budget.purchase','budget.po','budget.report','budget.refund_memo','inventory.manage','admin.approve','budget.approve','sar.own','budget.memo','budget.memo_approve'],
        'head_hr'        => ['hr.personnel','hr.leave','hr.attendance','hr.salary','hr.evaluation','admin.approve','sar.own','budget.memo'],
        'head_general'   => ['general.asset','general.document','general.repair','general.booking','general.vehicle','general.health','general.pr',
                             'document.manage','document.sign','admin.approve','sar.own','budget.memo'],
        'finance_officer'=> ['budget.ledger','budget.purchase','budget.po','budget.report','budget.refund_memo'],
        'inventory_officer'=>['general.asset','general.document','inventory.manage'],
        'clerk'          => ['general.asset','general.document','document.manage','document.sign','general.booking','admin.eoffice','budget.memo'],
        'teacher'        => ['academic.schedule','academic.grades','academic.attendance','academic.measurement','sar.own','budget.memo'],
        'advisor'        => ['academic.attendance','academic.attendance_report','student.behavior','student.care','student.profile','sar.own','budget.memo'],
        'student'        => ['portal.student'],
        'guardian'       => ['portal.guardian'],
        'board'          => ['admin.dashboard','admin.reports','budget.report'],
        'auditor'        => ['budget.ledger','audit.view','admin.reports','budget.report'],
    ],
];
