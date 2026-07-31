<?php
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\UserController;
use App\Controllers\RoleController;
use App\Controllers\PermissionController;
use App\Controllers\AuditLogController;
use App\Controllers\ProfileController;

/** @var Router $router */

// ---- สาธารณะ ----
$router->get('/',        [AuthController::class, 'showLogin']);
$router->get('/login',   [AuthController::class, 'showLogin']);
$router->post('/login',  [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);

// ---- ลืมรหัสผ่าน / รีเซตด้วยตนเองผ่านอีเมล (สาธารณะ) ----
$router->get('/forgot',          [AuthController::class, 'showForgot']);
$router->post('/forgot',         [AuthController::class, 'sendReset']);
$router->get('/reset/{token}',   [AuthController::class, 'showReset']);
$router->post('/reset/{token}',  [AuthController::class, 'doReset']);
// ระบบหลายโรงเรียน: ลิงก์รีเซตมีรหัสโรงเรียนกำกับ
$router->get('/reset/{code}/{token}',  [AuthController::class, 'showResetTenant']);
$router->post('/reset/{code}/{token}', [AuthController::class, 'doResetTenant']);

// ---- ศูนย์ควบคุมส่วนกลาง (ระบบหลายโรงเรียน) ----
$PLT  = \App\Controllers\PlatformController::class;
$plat = [\App\Middleware\PlatformMiddleware::class];
$router->get('/platform',        [$PLT, 'showLogin']);
$router->get('/platform/login',  [$PLT, 'showLogin']);
$router->post('/platform/login', [$PLT, 'login']);
$router->post('/platform/logout',[$PLT, 'logout'], $plat);
$router->get('/platform/tenants',     [$PLT, 'index'],  $plat);
$router->get('/platform/tenants/new', [$PLT, 'create'], $plat);
$router->post('/platform/tenants',    [$PLT, 'store'],  $plat);
$router->get('/platform/tenants/{id}',              [$PLT, 'show'],        $plat);
$router->post('/platform/tenants/{id}/info',        [$PLT, 'updateInfo'],  $plat);
$router->post('/platform/tenants/{id}/status',      [$PLT, 'setStatus'],   $plat);
$router->post('/platform/tenants/{id}/reset-admin', [$PLT, 'resetAdmin'],  $plat);
$router->post('/platform/tenants/{id}/enter',       [$PLT, 'enter'],       $plat);
$router->post('/platform/exit',      [$PLT, 'exit'],   $plat);
$router->get('/platform/logs',       [$PLT, 'logs'],   $plat);
$router->get('/platform/account',    [$PLT, 'account'],$plat);
$router->post('/platform/account/password', [$PLT, 'updatePassword'], $plat);

// ---- ต้องล็อกอิน ----
$auth = [AuthMiddleware::class];

$router->get('/dashboard', [DashboardController::class, 'index'], $auth);
$router->get('/workflow',  [DashboardController::class, 'workflow'], $auth);

// ---- โมดูลจริง (มีข้อมูล) ----
$router->get('/students',       [\App\Controllers\StudentController::class, 'index'], $auth);
$router->get('/students/export',          [\App\Controllers\StudentController::class, 'exportCsv'], $auth);
$router->get('/students/import',          [\App\Controllers\StudentController::class, 'importForm'], $auth);
$router->get('/students/import/template', [\App\Controllers\StudentController::class, 'importTemplate'], $auth);
$router->post('/students/import',         [\App\Controllers\StudentController::class, 'importUpload'], $auth);
// ---- สุขภาพนักเรียน (BMI/น้ำหนัก/ส่วนสูง) รายห้องเรียน ----
$HLT = \App\Controllers\HealthController::class;
$router->get('/health',                 [$HLT, 'index'], $auth);
$router->get('/health/{id}',            [$HLT, 'entry'], $auth);
$router->post('/health/{id}/save',      [$HLT, 'save'], $auth);
$router->get('/students/{id}',  [\App\Controllers\StudentController::class, 'show'],  $auth);
$router->get('/personnel',      [\App\Controllers\PersonnelController::class, 'index'], $auth);
$router->get('/personnel/{id}', [\App\Controllers\PersonnelController::class, 'show'],  $auth);
$router->get('/subjects',       [\App\Controllers\AcademicController::class, 'subjects'], $auth);

// ---- ฝ่ายวิชาการ (งานหลัก) ----
$ACAD = \App\Controllers\AcademicController::class;
$router->get('/subjects/import',          [$ACAD, 'importForm'], $auth);
$router->get('/subjects/template',        [$ACAD, 'template'], $auth);
$router->post('/subjects/import',         [$ACAD, 'importCsv'], $auth);
$router->get('/teaching',                 [$ACAD, 'assignments'], $auth);
$router->post('/teaching',                [$ACAD, 'assignmentStore'], $auth);
$router->post('/teaching/{id}/delete',     [$ACAD, 'assignmentDelete'], $auth);
$router->get('/scores',                   [$ACAD, 'scores'], $auth);
$router->get('/scores/{id}',              [$ACAD, 'scoreEntry'], $auth);
$router->get('/scores/{id}/structure',    [$ACAD, 'structure'], $auth);
$router->post('/scores/{id}/structure/ratio', [$ACAD, 'structureRatio'], $auth);
$router->get('/scores/{id}/evaluate',     [$ACAD, 'evaluate'], $auth);
$router->get('/scores/{id}/pp5',          [$ACAD, 'pp5'], $auth);
$router->post('/scores/{id}/evaluate/save',[$ACAD, 'evaluateSave'], $auth);
$router->post('/scores/{id}/evaluate/copy',[$ACAD, 'evaluateCopy'], $auth);
$router->post('/scores/{id}/component',   [$ACAD, 'componentStore'], $auth);
$router->post('/scores/{taId}/component/{id}/delete', [$ACAD, 'componentDelete'], $auth);
$router->post('/scores/{id}/save',        [$ACAD, 'scoreSave'], $auth);
$router->post('/scores/{id}/init',         [$ACAD, 'initComponents'], $auth);
$router->get('/grade-monitor',             [$ACAD, 'gradeMonitor'], $auth);
$router->post('/grade-monitor/ratio',      [$ACAD, 'ratioUpdate'], $auth);
$router->get('/grades',                   [$ACAD, 'grades'], $auth);
$router->get('/pp-transcript',            [$ACAD, 'transcriptList'], $auth);
$router->get('/transcript/{id}',          [$ACAD, 'transcript'], $auth);
$router->get('/transcript/{id}/print', [$ACAD, 'transcriptPrint'], $auth);
$router->get('/transcript/{id}/pp1',   [$ACAD, 'pp1'], $auth);
$router->get('/zero-rms',                 [$ACAD, 'zeroRms'], $auth);
$router->post('/zero-rms/fix',            [$ACAD, 'zeroRmsFix'], $auth);
$router->get('/zero-rms/wk01/{taId}',     [$ACAD, 'zeroRmsWk01'], $auth);
$router->get('/attendance',               [$ACAD, 'attendance'], $auth);
$router->get('/attendance/report',        [$ACAD, 'attendanceReport'], $auth);
$router->get('/attendance/report/pdf',    [$ACAD, 'reportExportPdf'], $auth);
$router->get('/attendance/report/excel',  [$ACAD, 'reportExportExcel'], $auth);
$router->get('/attendance/flag',          [$ACAD, 'flag'], $auth);
$router->post('/attendance/flag/settings',[$ACAD, 'flagSettingsSave'], $auth);
$router->get('/attendance/flag/{id}',     [$ACAD, 'flagEntry'], $auth);
$router->post('/attendance/flag/{id}/save',[$ACAD, 'flagSave'], $auth);
$router->post('/attendance/flag/{id}/all',[$ACAD, 'flagAllPresent'], $auth);
$router->get('/attendance/{id}',          [$ACAD, 'attendanceEntry'], $auth);
$router->post('/attendance/{id}/save',    [$ACAD, 'attendanceSave'], $auth);
$router->get('/schedule',                 [$ACAD, 'schedule'], $auth);
$TT = \App\Controllers\TimetableController::class;
$router->get('/schedule/build',           [$TT, 'build'], $auth);
$router->post('/schedule/auto',           [$TT, 'autoGenerate'], $auth);
$router->post('/schedule/clear',          [$TT, 'clear'], $auth);
$router->post('/schedule/place',          [$TT, 'place'], $auth);
$router->post('/schedule/unplace',        [$TT, 'unplace'], $auth);
$router->post('/schedule/lock',           [$TT, 'lock'], $auth);
$router->get('/schedule/dashboard',       [$TT, 'dashboard'], $auth);
$router->post('/schedule/publish',        [$TT, 'publish'], $auth);
$router->get('/schedule/export-excel',    [$TT, 'exportExcel'], $auth);
$router->get('/schedule/export-pdf',      [$TT, 'exportPdf'], $auth);
$router->post('/teaching/{id}/load',      [\App\Controllers\AcademicController::class, 'assignmentLoad'], $auth);
$router->get('/budget',         [\App\Controllers\BudgetController::class, 'index'], $auth);
$router->get('/assets',         [\App\Controllers\InventoryController::class, 'index'], $auth);



// ---- ศูนย์ฝ่ายงบประมาณ/พัสดุ (hub รวม flow) ----
$router->get('/budget-hub', [\App\Controllers\BudgetHubController::class, 'index'], $auth);

// ---- งป.01 บันทึกขออนุมัติดำเนินการ + ตัดงบ ----
$BM = \App\Controllers\BudgetMemoController::class;
$router->get('/budget-memo',                [$BM, 'index'], $auth);
$router->get('/budget-memo/create',         [$BM, 'create'], $auth);
$router->get('/budget-memo/report',         [$BM, 'report'], $auth);
$router->post('/budget-memo/store',         [$BM, 'store'], $auth);
$router->get('/budget-memo/activities/{projectId}', [$BM, 'activities'], $auth);
$router->get('/budget-memo/{id}',           [$BM, 'show'], $auth);
$router->get('/budget-memo/{id}/edit',      [$BM, 'edit'], $auth);
$router->post('/budget-memo/{id}/submit',   [$BM, 'submit'], $auth);
$router->post('/budget-memo/{id}/step',     [$BM, 'step'], $auth);
$router->post('/budget-memo/{id}/approve',  [$BM, 'approve'], $auth);
$router->post('/budget-memo/{id}/pay',      [$BM, 'pay'], $auth);
$router->post('/budget-memo/{id}/delete',   [$BM, 'delete'], $auth);
$router->get('/budget-memo/{id}/print',     [$BM, 'print'], $auth);

// ---- บันทึกขอคืนเงินยืมราชการ (เฉพาะฝ่ายการเงิน/งบประมาณ) ----
$RM = \App\Controllers\RefundMemoController::class;
$router->get('/refund-memo',              [$RM, 'index'], $auth);
$router->get('/refund-memo/{id}/edit',    [$RM, 'edit'], $auth);
$router->post('/refund-memo/{id}/update', [$RM, 'update'], $auth);
$router->get('/refund-memo/{id}/print',   [$RM, 'print'], $auth);

// ---- คลังแบบฟอร์ม E-Office · ใบเบิกค่าใช้จ่ายเดินทางไปราชการ (แบบ 8708) ----
$TV = \App\Controllers\TravelController::class;
$router->get('/travel',               [$TV, 'index'], $auth);
$router->get('/travel/create',        [$TV, 'create'], $auth);
$router->post('/travel',              [$TV, 'store'], $auth);
$router->get('/travel/{id}/edit',     [$TV, 'edit'], $auth);
$router->post('/travel/{id}/update',  [$TV, 'update'], $auth);
$router->post('/travel/{id}/delete',  [$TV, 'delete'], $auth);
$router->get('/travel/{id}/print',    [$TV, 'print'], $auth);

// ---- คลังแบบฟอร์ม E-Office · ใบสำคัญรับเงิน (งป.08) — กรอก→พิมพ์ ----
$RV = \App\Controllers\ReceiptVoucherController::class;
$router->get('/receipt-voucher',        [$RV, 'form'], $auth);
$router->post('/receipt-voucher/print', [$RV, 'generate'], $auth);
$router->get('/receipt-voucher/print',  [$RV, 'generate'], $auth);  // เปิด/รีเฟรชหน้าพิมพ์ได้ ไม่ 404

// ---- SAR รายงานประเมินตนเอง ----
$SAR = \App\Controllers\SarController::class;
$router->get('/sar',                  [$SAR, 'index'], $auth);
$router->post('/sar/start',           [$SAR, 'start'], $auth);
$router->get('/sar/report',           [$SAR, 'report'], $auth);
$router->get('/sar/{id}',             [$SAR, 'show'], $auth);
$router->get('/sar/{id}/edit',        [$SAR, 'edit'], $auth);
$router->post('/sar/{id}/save',       [$SAR, 'save'], $auth);
$router->post('/sar/{id}/submit',     [$SAR, 'submit'], $auth);
$router->post('/sar/{id}/review',     [$SAR, 'review'], $auth);
$router->post('/sar/{id}/upload',     [$SAR, 'upload'], $auth);
$router->get('/sar/{id}/print',       [$SAR, 'print'], $auth);
$router->post('/sar/file/{aid}/delete',[$SAR, 'fileDelete'], $auth);
$router->get('/sar/file/{aid}/download',[$SAR, 'download'], $auth);

// ---- SAR มาตรฐานการศึกษา ระดับปฐมวัย (11 มาตรฐาน) — งานบริหาร ----
$SKG = \App\Controllers\SarKindergartenController::class;
$router->get('/sar-kg',              [$SKG, 'index'], $auth);
$router->post('/sar-kg/start',       [$SKG, 'start'], $auth);
$router->get('/sar-kg/{id}/edit',    [$SKG, 'edit'], $auth);
$router->post('/sar-kg/{id}/save',   [$SKG, 'save'], $auth);
$router->get('/sar-kg/{id}/print',   [$SKG, 'print'], $auth);

// ---- นำเข้าฐานข้อมูลจากในระบบ (เฉพาะผู้ดูแลระบบ) ----
$SC = \App\Controllers\SetupController::class;
$router->get('/setup/migrations',          [$SC, 'migrations'], $auth);
$router->post('/setup/migrations/run',     [$SC, 'run'], $auth);
$router->post('/setup/migrations/mark',    [$SC, 'mark'], $auth);
$router->post('/setup/migrations/missing', [$SC, 'runMissing'], $auth);
$router->get('/setup/backup',              [$SC, 'backup'], $auth);
$router->post('/setup/backup/run',         [$SC, 'backupRun'], $auth);
$router->get('/setup/doc-reset',           [$SC, 'docReset'], $auth);
$router->post('/setup/doc-reset/run',      [$SC, 'docResetRun'], $auth);

// ตรวจสอบเอกสารจาก QR — สาธารณะ ไม่ต้องล็อกอิน
$router->get('/verify',        [\App\Controllers\DocumentController::class, 'verify']);
$router->get('/verify/{code}', [\App\Controllers\DocumentController::class, 'verify']);

// ---- งานสารบรรณ: ลงรับ · แนบไฟล์หลายไฟล์ · เกษียน · ผอ.สั่งการ+ลงนาม ----
$DC = \App\Controllers\DocumentController::class;
$router->get('/documents',                        [$DC, 'index'], $auth);
// literal ต้องมาก่อน {id} เสมอ
$router->get('/documents/register/print',         [$DC, 'registerPrint'], $auth);
$router->get('/documents/files/{id}/download',    [$DC, 'download'], $auth);
$router->post('/documents/files/{id}/delete',     [$DC, 'fileDelete'], $auth);
$router->post('/documents/notes/{id}/delete',     [$DC, 'noteDelete'], $auth);
$router->get('/documents/inbox',                  [$DC, 'inbox'], $auth);
$router->post('/documents/recipients/{id}/ack',   [$DC, 'recipientAck'], $auth);
$router->post('/documents/recipients/{id}/delete',[$DC, 'recipientDelete'], $auth);
$router->post('/documents',                       [$DC, 'store'], $auth);
$router->get('/documents/{id}',                   [$DC, 'show'], $auth);
$router->get('/documents/{id}/track',             [$DC, 'track'], $auth);
$router->get('/documents/{id}/cover/print',       [$DC, 'coverPrint'], $auth);
$router->post('/documents/{id}/update',           [$DC, 'update'], $auth);
$router->post('/documents/{id}/delete',           [$DC, 'delete'], $auth);
$router->post('/documents/{id}/receive',          [$DC, 'receive'], $auth);
$router->post('/documents/{id}/status',           [$DC, 'statusSet'], $auth);
$router->post('/documents/{id}/upload',           [$DC, 'upload'], $auth);
$router->post('/documents/{id}/note-assign',      [$DC, 'noteAssign'], $auth);
$router->post('/documents/{id}/note-order',       [$DC, 'noteOrder'], $auth);
$router->post('/documents/{id}/forward',          [$DC, 'forward'], $auth);

// ---- ฝ่ายบริหาร ----
$AC = \App\Controllers\AdminController::class;
$router->get('/exec/dashboard',        [$AC, 'dashboard'], $auth);
$router->get('/kpi',                   [$AC, 'kpi'], $auth);
$router->post('/kpi',                  [$AC, 'kpiStore'], $auth);
$router->post('/kpi/{id}',             [$AC, 'kpiUpdate'], $auth);
$router->post('/kpi/{id}/delete',      [$AC, 'kpiDelete'], $auth);
$router->get('/approvals',             [$AC, 'approvals'], $auth);
$router->post('/approvals/request',    [$AC, 'requestStore'], $auth);
$router->post('/approvals/{id}/decide',[$AC, 'requestDecide'], $auth);
$router->get('/official-docs',         [$AC, 'officialDocs'], $auth);
$router->get('/official-docs/{id}',    [$AC, 'officialDocShow'], $auth);
$router->get('/eoffice',               [$AC, 'eoffice'], $auth);
$router->get('/eoffice/{id}',          [$AC, 'eofficeCreate'], $auth);
$router->post('/eoffice/{id}/generate',[$AC, 'eofficeGenerate'], $auth);
$router->get('/calendar',              [$AC, 'calendar'], $auth);
$router->post('/calendar/event',       [$AC, 'eventStore'], $auth);
$router->post('/calendar/event/{id}',  [$AC, 'eventUpdate'], $auth);
$router->post('/calendar/event/{id}/delete', [$AC, 'eventDelete'], $auth);

// ---- งานวัดผล / คลังข้อสอบ ----
// ---- งานแผนและงบประมาณ ----
// ---- พอร์ทัลนักเรียน (ดูได้เฉพาะข้อมูลตนเอง) ----
$PO = \App\Controllers\PortalController::class;
$router->get('/my',                  [$PO, 'dashboard'], $auth);
$router->get('/my/grades',           [$PO, 'grades'], $auth);
$router->get('/my/grades/print',     [$PO, 'gradesPrint'], $auth);
$router->get('/my/classroom',        [$PO, 'classroom'], $auth);
$router->get('/my/timetable/print',  [$PO, 'timetablePrint'], $auth);
$router->get('/my/attendance',       [$PO, 'attendance'], $auth);
$router->get('/my/behavior',         [$PO, 'behavior'], $auth);
$router->get('/my-children',         [$PO, 'children'], $auth);   // พอร์ทัลผู้ปกครอง

// ---- รายงาน: ผลการดำเนินงาน + งบประมาณ ----
$RP = \App\Controllers\ReportController::class;
$router->get('/reports',              [$RP, 'admin'], $auth);
$router->get('/reports/print',        [$RP, 'adminPrint'], $auth);
$router->get('/reports/budget',       [$RP, 'budget'], $auth);
$router->get('/reports/budget/print', [$RP, 'budgetPrint'], $auth);

// ---- งานกิจการนักเรียน: พฤติกรรม/SDQ · เยี่ยมบ้าน · ทุนการศึกษา ----
$SA = \App\Controllers\StudentAffairsController::class;
$router->get('/affairs',                                [$SA, 'dashboard'], $auth);
// พฤติกรรม
$router->get('/affairs/behaviors',                      [$SA, 'behaviors'], $auth);
$router->post('/affairs/behaviors',                     [$SA, 'behaviorStore'], $auth);
$router->get('/affairs/behaviors/{id}/print',           [$SA, 'behaviorPrint'], $auth);
$router->get('/affairs/behaviors/{id}/invite',          [$SA, 'behaviorInvitePrint'], $auth);
$router->post('/affairs/behaviors/{id}/update',         [$SA, 'behaviorUpdate'], $auth);
$router->post('/affairs/behaviors/{id}/delete',         [$SA, 'behaviorDelete'], $auth);
$router->get('/affairs/students/{sid}/behavior-print',  [$SA, 'behaviorStudentPrint'], $auth);
// SDQ  (literal report/print ต้องมาก่อน {id})
$router->get('/affairs/sdq',                            [$SA, 'sdq'], $auth);
$router->get('/affairs/sdq/report/print',               [$SA, 'sdqReportPrint'], $auth);
$router->post('/affairs/sdq',                           [$SA, 'sdqStore'], $auth);
$router->get('/affairs/sdq/{id}/print',                 [$SA, 'sdqPrint'], $auth);
$router->post('/affairs/sdq/{id}/update',               [$SA, 'sdqUpdate'], $auth);
$router->post('/affairs/sdq/{id}/delete',               [$SA, 'sdqDelete'], $auth);
// เยี่ยมบ้าน
$router->get('/affairs/visits',                         [$SA, 'visits'], $auth);
$router->get('/affairs/visits/report/print',            [$SA, 'visitReportPrint'], $auth);
$router->post('/affairs/visits',                        [$SA, 'visitStore'], $auth);
$router->get('/affairs/visits/{id}/print',              [$SA, 'visitPrint'], $auth);
$router->post('/affairs/visits/{id}/update',            [$SA, 'visitUpdate'], $auth);
$router->post('/affairs/visits/{id}/delete',            [$SA, 'visitDelete'], $auth);
// ทุนการศึกษา
$router->get('/affairs/scholarships',                   [$SA, 'scholarships'], $auth);
$router->get('/affairs/scholarships/report/print',      [$SA, 'scholarshipReportPrint'], $auth);
$router->post('/affairs/scholarships',                  [$SA, 'scholarshipStore'], $auth);
$router->get('/affairs/scholarships/{id}/print',        [$SA, 'scholarshipPrint'], $auth);
$router->post('/affairs/scholarships/{id}/update',      [$SA, 'scholarshipUpdate'], $auth);
$router->post('/affairs/scholarships/{id}/delete',      [$SA, 'scholarshipDelete'], $auth);
$router->post('/affairs/scholarships/{id}/decide',      [$SA, 'scholarshipDecide'], $auth);
$router->post('/affairs/scholarships/{id}/grant',       [$SA, 'scholarshipGrant'], $auth);

// ---- ติดตามโครงการ + ตั้งค่าระบบ ----
$SET = \App\Controllers\SettingController::class;
$router->get('/settings',                       [$SET, 'index'], $auth);
$router->post('/settings/school',               [$SET, 'schoolSave'], $auth);
$router->post('/settings/branding',             [$SET, 'brandingSave'], $auth);
$router->post('/settings/app-branding',         [$SET, 'appBrandingSave'], $auth);
$router->post('/settings/save',                 [$SET, 'save'], $auth);
$router->post('/settings/stages',               [$SET, 'stagesSave'], $auth);
$router->post('/settings/year',                 [$SET, 'yearAdd'], $auth);
$router->post('/settings/year/{id}/current',    [$SET, 'yearCurrent'], $auth);
$router->post('/settings/year/{id}/delete',     [$SET, 'yearDelete'], $auth);
$router->post('/settings/semester/{id}/current',[$SET, 'semesterCurrent'], $auth);

$PL = \App\Controllers\PlanningController::class;
$router->get('/planning',                       [$PL, 'index'], $auth);
$router->get('/planning/exec',                  [$PL, 'execReport'], $auth);
$router->get('/planning/exec/data',             [$PL, 'execData'], $auth);
$router->get('/projects',                   [$PL, 'tracking'], $auth);
$router->get('/projects/print',             [$PL, 'trackingPrint'], $auth);
$router->post('/projects',                  [$PL, 'projectStore'], $auth);
$router->post('/projects/{id}/update',      [$PL, 'projectUpdate'], $auth);
$router->post('/projects/{id}/progress',    [$PL, 'projectProgress'], $auth);
$router->post('/projects/{id}/approve',     [$PL, 'projectApprove'], $auth);
$router->get('/projects/{id}/proposal',     [$PL, 'projectProposal'], $auth);
$router->get('/projects/{id}/result',       [$PL, 'projectResult'], $auth);
$router->post('/projects/{id}/delete',      [$PL, 'projectDelete'], $auth);
$router->get('/planning/ledger',            [$PL, 'ledger'], $auth);
$router->get('/planning/ledger/print',      [$PL, 'ledgerPrint'], $auth);
$router->get('/planning/requests',              [$PL, 'requests'], $auth);
$router->post('/planning/requests',             [$PL, 'requestStore'], $auth);
$router->get('/planning/requests/{id}',         [$PL, 'requestShow'], $auth);
$router->post('/planning/requests/{id}/decide', [$PL, 'requestDecide'], $auth);
$router->post('/planning/requests/{id}/pay',    [$PL, 'requestPay'], $auth);
$router->post('/planning/requests/{id}/cancel', [$PL, 'requestCancel'], $auth);
$router->post('/planning/requests/{id}/refund', [$PL, 'requestRefund'], $auth);
$router->post('/planning/requests/{id}/delete', [$PL, 'requestDelete'], $auth);
$router->get('/planning/requests/{id}/print',   [$PL, 'requestPrint'], $auth);
$router->get('/planning/advances',              [$PL, 'advances'], $auth);
$router->post('/planning/advances',             [$PL, 'advanceStore'], $auth);
$router->get('/planning/advances/{id}',         [$PL, 'advanceShow'], $auth);
$router->post('/planning/advances/{id}/decide', [$PL, 'advanceDecide'], $auth);
$router->post('/planning/advances/{id}/pay',    [$PL, 'advancePay'], $auth);
$router->post('/planning/advances/{id}/clear',  [$PL, 'advanceClear'], $auth);
$router->post('/planning/advances/{id}/delete', [$PL, 'advanceDelete'], $auth);
$router->get('/planning/advances/{id}/print',   [$PL, 'advancePrint'], $auth);
$router->get('/planning/{id}',                  [$PL, 'project'], $auth);
$router->post('/planning/{id}/activity',        [$PL, 'activityStore'], $auth);
$router->post('/planning/{id}/activity/{aid}/delete', [$PL, 'activityDelete'], $auth);

$EX = \App\Controllers\ExamController::class;
$router->get('/exams',                    [$EX, 'index'], $auth);
$router->get('/exams/create',             [$EX, 'create'], $auth);
$router->post('/exams',                   [$EX, 'store'], $auth);
$router->get('/exams/{id}',               [$EX, 'show'], $auth);
$router->get('/exams/{id}/edit',          [$EX, 'edit'], $auth);
$router->post('/exams/{id}/update',       [$EX, 'update'], $auth);
$router->post('/exams/{id}/delete',       [$EX, 'destroy'], $auth);
$router->get('/exams/{id}/answer-sheet',  [$EX, 'answerSheet'], $auth);
$router->get('/exams/{id}/cover',         [$EX, 'cover'], $auth);
$router->get('/exams/{id}/download',      [$EX, 'download'], $auth);
// ── เฉลย · ตรวจคำตอบผ่านระบบ · กระดาษคำตอบรายบุคคล ──
$router->get('/exams/{id}/answer-key',    [$EX, 'answerKey'], $auth);
$router->post('/exams/{id}/answer-key',   [$EX, 'answerKeySave'], $auth);
$router->post('/exams/{id}/set-class',    [$EX, 'setClass'], $auth);
$router->get('/exams/{id}/grade',         [$EX, 'grade'], $auth);
$router->post('/exams/{id}/grade',        [$EX, 'gradeSave'], $auth);
$router->get('/exams/{id}/grade/{sid}',   [$EX, 'gradeStudent'], $auth);
$router->get('/exams/{id}/sheets',        [$EX, 'sheets'], $auth);
$router->get('/exams/{id}/scan',          [$EX, 'scan'], $auth);
$router->get('/exams/{id}/scan-sheet',    [$EX, 'scanSheet'], $auth);

// ---- ประเมินพัฒนาการอนุบาล ----
$KG = \App\Controllers\KindergartenController::class;
$router->get('/kindergarten',        [$KG, 'index'], $auth);
$router->post('/kindergarten/save',  [$KG, 'save'], $auth);
$router->post('/kindergarten/indicators',            [$KG, 'indicatorAdd'], $auth);
$router->post('/kindergarten/indicators/{id}/delete',[$KG, 'indicatorDelete'], $auth);
$router->get('/kindergarten/print',  [$KG, 'print'], $auth);
$router->get('/kindergarten/report/{sid}', [$KG, 'report'], $auth);

// ---- สรุปยอดนักเรียน (admin + ผู้บริหาร + ครู) ----
$HC = \App\Controllers\StudentHeadcountController::class;
$router->get('/headcount',             [$HC, 'index'], $auth);
$router->get('/headcount/print',       [$HC, 'print'], $auth);
$router->get('/headcount/export',      [$HC, 'exportCsv'], $auth);
$router->get('/headcount/roster',      [$HC, 'roster'], $auth);
$router->get('/headcount/roster/export',[$HC, 'rosterCsv'], $auth);

// ---- บันทึกหลังการสอน (ดึงข้อมูลจากระบบ + CRUD) ----
$TL = \App\Controllers\TeachingLogController::class;
$router->get('/teaching-log',              [$TL, 'index'], $auth);
$router->get('/teaching-log/create',       [$TL, 'create'], $auth);
$router->post('/teaching-log',             [$TL, 'store'], $auth);
$router->get('/teaching-log/{id}/edit',    [$TL, 'edit'], $auth);
$router->post('/teaching-log/{id}/update', [$TL, 'update'], $auth);
$router->post('/teaching-log/{id}/delete', [$TL, 'delete'], $auth);
$router->get('/teaching-log/{id}/print',   [$TL, 'print'], $auth);

// ---- ฐานข้อมูลฝ่ายวิชาการ: ชั้นเรียน / ครู / แมพนักเรียน ----
$CM = \App\Controllers\ClassManageController::class;
$router->get('/classes',                  [$CM, 'classes'], $auth);
$router->get('/classes/{id}',             [$CM, 'classShow'], $auth);
$router->get('/classes/{id}/namelist',     [$CM, 'namelistPrint'], $auth);
$router->get('/classes/{id}/pp6',          [$CM, 'pp6'], $auth);
$router->post('/classes/{id}/enroll',     [$CM, 'enroll'], $auth);
$router->post('/classes/{id}/unenroll/{sid}', [$CM, 'unenroll'], $auth);
$router->get('/academic/students',        [$ACAD, 'students'], $auth);
$router->post('/academic/students/{id}/assign', [$ACAD, 'studentAssign'], $auth);
// ---- จัดสอนแทน (วันที่ครูลา) ----
$SUB = \App\Controllers\SubstituteController::class;
$router->get('/substitute',               [$SUB, 'index'], $auth);
$router->post('/substitute/assign',       [$SUB, 'assign'], $auth);
$router->post('/substitute/remove',       [$SUB, 'remove'], $auth);
$router->post('/substitute/auto',         [$SUB, 'auto'], $auth);
$router->post('/substitute/approve',      [$SUB, 'approve'], $auth);
$router->get('/teachers',                 [$CM, 'teachers'], $auth);
$router->post('/teachers/unavail',        [$CM, 'unavailStore'], $auth);
$router->post('/teachers/unavail/{id}/delete', [$CM, 'unavailDelete'], $auth);
$router->post('/teachers/{id}/config',    [$CM, 'teacherConfig'], $auth);
// ตั้งค่าเงื่อนไขจัดตาราง (admin)
$router->get('/schedule/settings',        [\App\Controllers\TimetableController::class, 'settingsPage'], $auth);
$router->post('/schedule/settings',       [\App\Controllers\TimetableController::class, 'settingsSave'], $auth);

// ---- จัดซื้อจัดจ้าง (ระเบียบพัสดุ 2560) ----
// ---- งานฝ่ายบุคลากร (HR) ----
// ---- ฝ่ายบริหารทั่วไป ----
$GN = \App\Controllers\GeneralController::class;
$router->get('/general',                       [$GN, 'dashboard'], $auth);
$router->get('/general/repairs',               [$GN, 'repairs'], $auth);
$router->post('/general/repairs',              [$GN, 'repairStore'], $auth);
$router->post('/general/repairs/{id}/update',  [$GN, 'repairUpdate'], $auth);
$router->post('/general/repairs/{id}/delete',  [$GN, 'repairDelete'], $auth);
$router->get('/general/assets',                [$GN, 'assets'], $auth);
$router->post('/general/assets',               [$GN, 'assetStore'], $auth);
$router->get('/general/assets/report',          [$GN, 'assetReport'], $auth);
$router->get('/general/assets/report/print',    [$GN, 'assetReportPrint'], $auth);
$router->get('/general/assets/{id}/label',     [$GN, 'assetPrint'], $auth);
$router->get('/general/assets/{id}/register',   [$GN, 'assetRegister'], $auth);
$router->post('/general/assets/{id}/update',   [$GN, 'assetUpdate'], $auth);
$router->post('/general/assets/{id}/location', [$GN, 'assetLocation'], $auth);
$router->post('/general/assets/{id}/delete',   [$GN, 'assetDelete'], $auth);
$router->get('/general/documents',             [$GN, 'documents'], $auth);
$router->post('/general/documents',            [$GN, 'documentStore'], $auth);
$router->post('/general/documents/{id}/update',[$GN, 'documentUpdate'], $auth);
$router->post('/general/documents/{id}/delete',[$GN, 'documentDelete'], $auth);
$router->get('/general/vehicles',              [$GN, 'vehicles'], $auth);
$router->post('/general/vehicles',             [$GN, 'vehicleStore'], $auth);
$router->post('/general/vehicles/{id}/update', [$GN, 'vehicleUpdate'], $auth);
$router->post('/general/vehicles/{id}/delete', [$GN, 'vehicleDelete'], $auth);
// ---- ร้านค้าสวัสดิการ: บัญชีรายรับ-รายจ่าย (general.welfare) ----
$WF = \App\Controllers\WelfareController::class;
$router->get('/general/welfare',                    [$WF, 'index'], $auth);
$router->post('/general/welfare',                   [$WF, 'store'], $auth);
$router->get('/general/welfare/report',             [$WF, 'report'], $auth);
$router->post('/general/welfare/{id}/delete',       [$WF, 'delete'], $auth);

// ---- งานพยาบาล/ห้องพยาบาล + คลังยา (general.health) ----
$NUR = \App\Controllers\NurseController::class;
$router->get('/general/nurse',                 [$NUR, 'index'], $auth);
$router->post('/general/nurse',                [$NUR, 'store'], $auth);
$router->get('/general/nurse/report',          [$NUR, 'report'], $auth);
$router->post('/general/nurse/{id}/delete',    [$NUR, 'delete'], $auth);
$router->get('/general/medicines',             [$NUR, 'medicines'], $auth);
$router->post('/general/medicines',            [$NUR, 'medStore'], $auth);
$router->get('/general/medicines/print',       [$NUR, 'medPrint'], $auth);
$router->post('/general/medicines/{id}/update',[$NUR, 'medUpdate'], $auth);
$router->post('/general/medicines/{id}/stock', [$NUR, 'medStock'], $auth);
$router->post('/general/medicines/{id}/delete',[$NUR, 'medDelete'], $auth);

$router->get('/general/bookings',              [$GN, 'bookings'], $auth);
$router->post('/general/bookings',             [$GN, 'bookingStore'], $auth);
$router->get('/general/bookings/{id}/print',   [$GN, 'bookingPrint'], $auth);
$router->post('/general/bookings/{id}/decide', [$GN, 'bookingDecide'], $auth);
$router->post('/general/bookings/{id}/complete',[$GN, 'bookingComplete'], $auth);
$router->post('/general/bookings/{id}/delete', [$GN, 'bookingDelete'], $auth);

$HR = \App\Controllers\HrController::class;
$router->get('/hr',                     [$HR, 'dashboard'], $auth);
$router->get('/hr/leaves',              [$HR, 'leaves'], $auth);
$router->post('/hr/leaves',             [$HR, 'leaveStore'], $auth);
$router->post('/hr/leaves/{id}/decide', [$HR, 'leaveDecide'], $auth);
$router->get('/hr/leaves/{id}/edit',    [$HR, 'leaveEdit'], $auth);
$router->post('/hr/leaves/{id}/update', [$HR, 'leaveUpdate'], $auth);
$router->post('/hr/leaves/{id}/delete', [$HR, 'leaveDelete'], $auth);
$router->get('/hr/leaves/{id}/print',   [$HR, 'leavePrint'], $auth);
$router->get('/hr/attendance',          [$HR, 'attendance'], $auth);
$router->post('/hr/attendance/save',    [$HR, 'attendanceSave'], $auth);
$router->get('/hr/attendance/manage',   [$HR, 'attendanceManage'], $auth);
$router->post('/hr/attendance/{id}/delete', [$HR, 'attendanceDelete'], $auth);
$router->get('/hr/salary',              [$HR, 'salary'], $auth);
$router->post('/hr/salary',             [$HR, 'salaryStore'], $auth);
$router->get('/hr/salary/{id}/slip',    [$HR, 'payslip'], $auth);
$router->post('/hr/salary/{id}/delete', [$HR, 'salaryDelete'], $auth);
$router->get('/hr/sar',                 [$HR, 'sar'], $auth);
$router->post('/hr/sar/print',          [$HR, 'sarPrint'], $auth);
$router->get('/hr/pa',                  [$HR, 'pa'], $auth);
$router->post('/hr/pa',                 [$HR, 'paStore'], $auth);
$router->post('/hr/pa/{id}/delete',     [$HR, 'paDelete'], $auth);

// ---- รายงานผลกิจกรรม/การแข่งขัน (activity.report — ครูทุกคน) ----
$AC = \App\Controllers\ActivityController::class;
$router->get('/activities',                    [$AC, 'index'], $auth);
$router->post('/activities',                   [$AC, 'store'], $auth);
$router->get('/activities/summary/print',      [$AC, 'summaryPrint'], $auth);
$router->get('/activities/files/{fid}/download',[$AC, 'fileDownload'], $auth);
$router->post('/activities/files/{fid}/delete',[$AC, 'fileDelete'], $auth);
$router->post('/activities/participants/{pid}/delete',[$AC, 'participantDelete'], $auth);
$router->get('/activities/{id}',               [$AC, 'show'], $auth);
$router->get('/activities/{id}/print',         [$AC, 'print'], $auth);
$router->get('/activities/{id}/memo',          [$AC, 'memo'], $auth);
$router->post('/activities/{id}/memo',         [$AC, 'memoSave'], $auth);
$router->post('/activities/{id}/update',       [$AC, 'update'], $auth);
$router->post('/activities/{id}/delete',       [$AC, 'delete'], $auth);
$router->post('/activities/{id}/participants', [$AC, 'participantStore'], $auth);
$router->post('/activities/{id}/files',        [$AC, 'fileUpload'], $auth);

// ---- PA ของฉัน: บุคลากรตั้งหัวข้อ PA + แนบ PDF (pa.own) ----
$PA = \App\Controllers\PaController::class;
$router->get('/pa',                     [$PA, 'index'], $auth);
$router->post('/pa',                    [$PA, 'store'], $auth);
$router->get('/pa/files/{fid}/download',[$PA, 'fileDownload'], $auth);
$router->post('/pa/files/{fid}/delete', [$PA, 'fileDelete'], $auth);
$router->post('/pa/{id}/update',        [$PA, 'update'], $auth);
$router->post('/pa/{id}/upload',        [$PA, 'upload'], $auth);
$router->post('/pa/{id}/delete',        [$PA, 'delete'], $auth);

$PC = \App\Controllers\ProcurementController::class;
$router->get('/purchase',              [$PC, 'purchase'], $auth);
$router->post('/purchase',             [$PC, 'prStore'], $auth);
$router->get('/purchase/{id}',         [$PC, 'prShow'], $auth);
$router->get('/purchase/{id}/print',    [$PC, 'prPrint'], $auth);
$router->post('/purchase/{id}/decide', [$PC, 'prDecide'], $auth);
$router->get('/po',                    [$PC, 'po'], $auth);
$router->post('/po',                   [$PC, 'poStore'], $auth);
$router->get('/po/{id}',               [$PC, 'poShow'], $auth);
$router->get('/po/{id}/print',          [$PC, 'poPrint'], $auth);
$router->get('/po/{id}/settlement',      [$PC, 'poSettlementPrint'], $auth);
$router->post('/po/{id}/receive',      [$PC, 'poReceive'], $auth);
$router->post('/po/{id}/pay',          [$PC, 'poPay'], $auth);
$router->post('/po/{id}/cancel',       [$PC, 'poCancel'], $auth);
$router->post('/po/{id}/refund',   [$PC, 'poRefund'], $auth);

// ---- CRUD กลาง (เพิ่ม/แก้ไข/ลบ ทุกตาราง) ----
$CR = \App\Controllers\CrudController::class;
$router->get('/crud/{entity}/create',        [$CR, 'createForm'], $auth);
$router->post('/crud/{entity}/create',       [$CR, 'store'], $auth);
$router->get('/crud/{entity}/{id}/edit',     [$CR, 'editForm'], $auth);
$router->post('/crud/{entity}/{id}/edit',    [$CR, 'update'], $auth);
$router->post('/crud/{entity}/{id}/delete',  [$CR, 'delete'], $auth);

// โมดูลอยู่ระหว่างพัฒนา (placeholder กันด้วยสิทธิ์)
$router->get('/m/{slug}', [\App\Controllers\ModuleController::class, 'show'], $auth);

// ผู้ใช้งาน
$router->get('/users',              [UserController::class, 'index'],   $auth);
$router->get('/users/create',       [UserController::class, 'create'],  $auth);
$router->get('/users/bulk',         [UserController::class, 'createBulk'], $auth);
$router->post('/users/bulk',        [UserController::class, 'storeBulk'], $auth);
$router->get('/users/import-template', [UserController::class, 'importTemplate'], $auth);
$router->post('/users',             [UserController::class, 'store'],   $auth);
$router->get('/users/{id}',         [UserController::class, 'show'],    $auth);
$router->get('/users/{id}/edit',    [UserController::class, 'edit'],    $auth);
$router->post('/users/{id}',        [UserController::class, 'update'],  $auth);
$router->post('/users/{id}/delete', [UserController::class, 'destroy'], $auth);
$router->post('/users/{id}/unlock', [UserController::class, 'unlock'],  $auth);
$router->post('/users/{id}/reset-password', [UserController::class, 'resetPassword'], $auth);

// บทบาท/สิทธิ์
$router->get('/roles',              [RoleController::class, 'index'],   $auth);
$router->get('/roles/create',       [RoleController::class, 'create'],  $auth);
$router->post('/roles',             [RoleController::class, 'store'],   $auth);
$router->get('/roles/{id}/edit',    [RoleController::class, 'edit'],    $auth);
$router->post('/roles/{id}',        [RoleController::class, 'update'],  $auth);
$router->post('/roles/{id}/delete', [RoleController::class, 'destroy'], $auth);

$router->get('/permissions', [PermissionController::class, 'index'], $auth);

// Audit log
$router->get('/audit-logs', [AuditLogController::class, 'index'], $auth);

// โปรไฟล์
$router->get('/profile',          [ProfileController::class, 'index'],          $auth);
$router->post('/profile/password',[ProfileController::class, 'updatePassword'], $auth);
$router->post('/profile/signature',[ProfileController::class, 'signatureSave'], $auth);
// ---- การแจ้งเตือน ----
$NOT = \App\Controllers\NotificationController::class;
$router->get('/notifications',            [$NOT, 'index'], $auth);
$router->post('/notifications/read-all',  [$NOT, 'readAll'], $auth);
$router->post('/notifications/{id}/read', [$NOT, 'read'], $auth);
