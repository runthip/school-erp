<?php
/**
 * นิยาม CRUD กลางของทุกตารางข้อมูล — ใช้กับ CrudController + views/crud/form.php
 * field types: text | number | date | select | textarea
 * options: array คงที่ | options_sql: [sql, id_col, label_col]
 */
return [

 'students' => [
   'table'=>'students','label'=>'นักเรียน','perm'=>'student.profile','soft'=>true,
   'back'=>'students','defaults'=>['school_id'=>1],
   'fields'=>[
     ['name'=>'student_code','label'=>'รหัสนักเรียน','type'=>'text','required'=>true],
     ['name'=>'prefix','label'=>'คำนำหน้า','type'=>'select','options'=>['เด็กชาย'=>'เด็กชาย','เด็กหญิง'=>'เด็กหญิง','นาย'=>'นาย','นางสาว'=>'นางสาว']],
     ['name'=>'first_name','label'=>'ชื่อ','type'=>'text','required'=>true],
     ['name'=>'last_name','label'=>'นามสกุล','type'=>'text','required'=>true],
     ['name'=>'nickname','label'=>'ชื่อเล่น','type'=>'text'],
     ['name'=>'gender','label'=>'เพศ','type'=>'select','options'=>['male'=>'ชาย','female'=>'หญิง','other'=>'อื่น ๆ']],
     ['name'=>'birth_date','label'=>'วันเกิด','type'=>'date'],
     ['name'=>'blood_group','label'=>'กรุ๊ปเลือด','type'=>'select','options'=>['A'=>'A','B'=>'B','AB'=>'AB','O'=>'O']],
     ['name'=>'religion','label'=>'ศาสนา','type'=>'text'],
     ['name'=>'phone','label'=>'เบอร์โทร','type'=>'text'],
     ['name'=>'status','label'=>'สถานะ','type'=>'select','required'=>true,
      'options'=>['studying'=>'กำลังศึกษา','graduated'=>'จบการศึกษา','transferred'=>'ย้าย/ลาออก','dropped'=>'พ้นสภาพ','suspended'=>'พักการเรียน']],
   ],
 ],

 'personnel' => [
   'table'=>'personnel','label'=>'บุคลากร','perm'=>'hr.personnel','soft'=>true,
   'back'=>'personnel','defaults'=>['school_id'=>1],
   'fields'=>[
     ['name'=>'employee_code','label'=>'รหัสบุคลากร','type'=>'text','required'=>true],
     ['name'=>'prefix','label'=>'คำนำหน้า','type'=>'select','options'=>['นาย'=>'นาย','นาง'=>'นาง','นางสาว'=>'นางสาว']],
     ['name'=>'first_name','label'=>'ชื่อ','type'=>'text','required'=>true],
     ['name'=>'last_name','label'=>'นามสกุล','type'=>'text','required'=>true],
     ['name'=>'gender','label'=>'เพศ','type'=>'select','options'=>['male'=>'ชาย','female'=>'หญิง','other'=>'อื่น ๆ']],
     ['name'=>'position','label'=>'ตำแหน่ง','type'=>'text'],
     ['name'=>'academic_standing','label'=>'วิทยฐานะ','type'=>'text'],
     ['name'=>'department_id','label'=>'ฝ่าย/กลุ่มงาน','type'=>'select','options_sql'=>["SELECT id, name FROM org_departments ORDER BY id",'id','name']],
     ['name'=>'subject_group_id','label'=>'กลุ่มสาระ','type'=>'select','options_sql'=>["SELECT id, name FROM subject_groups ORDER BY id",'id','name']],
     ['name'=>'employment_type','label'=>'ประเภท','type'=>'select','options'=>['civil_servant'=>'ข้าราชการ','government_employee'=>'พนักงานราชการ','contract'=>'พนักงานจ้าง','other'=>'อื่น ๆ']],
     ['name'=>'phone','label'=>'เบอร์โทร','type'=>'text'],
     ['name'=>'email','label'=>'อีเมล','type'=>'text'],
     ['name'=>'status','label'=>'สถานะ','type'=>'select','required'=>true,
      'options'=>['active'=>'ปฏิบัติงาน','on_leave'=>'ลา','resigned'=>'ลาออก','retired'=>'เกษียณ']],
   ],
 ],

 'subjects' => [
   'table'=>'subjects','label'=>'รายวิชา','perm'=>'academic.curriculum','soft'=>false,
   'back'=>'subjects','defaults'=>['school_id'=>1,'is_active'=>1],
   'fields'=>[
     ['name'=>'subject_code','label'=>'รหัสวิชา','type'=>'text','required'=>true],
     ['name'=>'name_th','label'=>'ชื่อวิชา (ไทย)','type'=>'text','required'=>true],
     ['name'=>'subject_group_id','label'=>'กลุ่มสาระ','type'=>'select','options_sql'=>["SELECT id, name FROM subject_groups ORDER BY id",'id','name']],
     ['name'=>'credit','label'=>'หน่วยกิต','type'=>'number','step'=>'0.5','required'=>true],
     ['name'=>'hours_per_week','label'=>'คาบ/สัปดาห์','type'=>'number','step'=>'1'],
     ['name'=>'subject_type','label'=>'ประเภท','type'=>'select','required'=>true,
      'options'=>['core'=>'พื้นฐาน','additional'=>'เพิ่มเติม','activity'=>'กิจกรรม']],
     ['name'=>'is_active','label'=>'เปิดใช้งาน','type'=>'select','options'=>['1'=>'ใช้งาน','0'=>'ปิด']],
   ],
 ],

 'budgets' => [
   'table'=>'budgets','label'=>'งบประมาณ','perm'=>'budget.manage','soft'=>false,
   'back'=>'budget','defaults'=>['academic_year_id'=>1],
   'fields'=>[
     ['name'=>'budget_source_id','label'=>'แหล่งงบ','type'=>'select','required'=>true,
      'options_sql'=>["SELECT id, name FROM budget_sources ORDER BY id",'id','name']],
     ['name'=>'name','label'=>'ชื่อรายการงบ','type'=>'text','required'=>true],
     ['name'=>'allocated_amount','label'=>'จัดสรร (บาท)','type'=>'number','step'=>'0.01','required'=>true],
     ['name'=>'used_amount','label'=>'ใช้ไป (บาท)','type'=>'number','step'=>'0.01'],
   ],
 ],

 'projects' => [
   'table'=>'projects','label'=>'โครงการ','perm'=>'budget.manage','soft'=>false,
   'back'=>'budget','defaults'=>['school_id'=>1],
   'fields'=>[
     ['name'=>'code','label'=>'รหัสโครงการ','type'=>'text'],
     ['name'=>'name','label'=>'ชื่อโครงการ','type'=>'text','required'=>true],
     ['name'=>'budget_id','label'=>'ใช้งบจาก','type'=>'select','options_sql'=>["SELECT id, name FROM budgets ORDER BY id",'id','name']],
     ['name'=>'department_id','label'=>'ฝ่ายรับผิดชอบ','type'=>'select','options_sql'=>["SELECT id, name FROM org_departments ORDER BY id",'id','name']],
     ['name'=>'responsible_id','label'=>'ผู้รับผิดชอบ','type'=>'select','options_sql'=>["SELECT id, CONCAT(first_name,' ',last_name) AS name FROM personnel WHERE deleted_at IS NULL ORDER BY first_name",'id','name']],
     ['name'=>'budget_amount','label'=>'งบโครงการ (บาท)','type'=>'number','step'=>'0.01'],
     ['name'=>'start_date','label'=>'วันเริ่ม','type'=>'date'],
     ['name'=>'end_date','label'=>'วันสิ้นสุด','type'=>'date'],
     ['name'=>'progress_percent','label'=>'ความคืบหน้า (%)','type'=>'number','step'=>'1'],
     ['name'=>'status','label'=>'สถานะ','type'=>'select','required'=>true,
      'options'=>['planned'=>'วางแผน','ongoing'=>'กำลังดำเนินการ','completed'=>'เสร็จสิ้น','cancelled'=>'ยกเลิก']],
   ],
 ],

 'assets' => [
   'table'=>'assets','label'=>'ครุภัณฑ์','perm'=>'inventory.manage','soft'=>false,
   'back'=>'assets','defaults'=>['school_id'=>1],
   'fields'=>[
     ['name'=>'asset_code','label'=>'เลขครุภัณฑ์','type'=>'text','required'=>true],
     ['name'=>'name','label'=>'รายการ','type'=>'text','required'=>true],
     ['name'=>'category_id','label'=>'หมวด','type'=>'select','options_sql'=>["SELECT id, name FROM asset_categories ORDER BY id",'id','name']],
     ['name'=>'barcode','label'=>'Barcode','type'=>'text'],
     ['name'=>'qr_code','label'=>'QR Code','type'=>'text'],
     ['name'=>'acquired_date','label'=>'วันที่ได้มา','type'=>'date'],
     ['name'=>'acquired_price','label'=>'ราคา (บาท)','type'=>'number','step'=>'0.01'],
     ['name'=>'budget_source_id','label'=>'แหล่งงบ','type'=>'select','options_sql'=>["SELECT id, name FROM budget_sources ORDER BY id",'id','name']],
     ['name'=>'location_room_id','label'=>'ที่ตั้ง (ห้อง)','type'=>'select','options_sql'=>["SELECT id, name FROM rooms ORDER BY id",'id','name']],
     ['name'=>'responsible_id','label'=>'ผู้รับผิดชอบ','type'=>'select','options_sql'=>["SELECT id, CONCAT(first_name,' ',last_name) AS name FROM personnel WHERE deleted_at IS NULL ORDER BY first_name",'id','name']],
     ['name'=>'condition_status','label'=>'สภาพ','type'=>'select','required'=>true,
      'options'=>['normal'=>'ปกติ','repair'=>'ซ่อม','damaged'=>'ชำรุด','disposed'=>'จำหน่าย']],
   ],
 ],

 'materials' => [
   'table'=>'materials','label'=>'วัสดุ','perm'=>'inventory.manage','soft'=>false,
   'back'=>'assets','defaults'=>['school_id'=>1],
   'fields'=>[
     ['name'=>'code','label'=>'รหัสวัสดุ','type'=>'text','required'=>true],
     ['name'=>'name','label'=>'รายการ','type'=>'text','required'=>true],
     ['name'=>'unit','label'=>'หน่วยนับ','type'=>'text','required'=>true],
     ['name'=>'stock_qty','label'=>'คงเหลือ','type'=>'number','step'=>'0.01'],
     ['name'=>'min_qty','label'=>'ขั้นต่ำ','type'=>'number','step'=>'0.01'],
   ],
 ],

 'classrooms' => [
   'table'=>'classrooms','label'=>'ชั้นเรียน','perm'=>'academic.curriculum','soft'=>false,
   'back'=>'classes','defaults'=>['school_id'=>1,'academic_year_id'=>1],
   'fields'=>[
     ['name'=>'grade_level_id','label'=>'ระดับชั้น','type'=>'select','required'=>true,
      'options_sql'=>["SELECT id, name FROM grade_levels ORDER BY level_order",'id','name']],
     ['name'=>'section','label'=>'ห้องที่ (เลข)','type'=>'number','step'=>'1','required'=>true],
     ['name'=>'name','label'=>'ชื่อห้อง (เช่น ม.1/2)','type'=>'text','required'=>true],
     ['name'=>'room_id','label'=>'ห้องประจำ','type'=>'select','options_sql'=>["SELECT id, name FROM rooms WHERE room_type='classroom' ORDER BY name",'id','name']],
     ['name'=>'homeroom_teacher_id','label'=>'ครูที่ปรึกษา','type'=>'select',
      'options_sql'=>["SELECT id, CONCAT(first_name,' ',last_name) AS name FROM personnel WHERE deleted_at IS NULL AND status='active' ORDER BY first_name",'id','name']],
   ],
 ],

 'vendors' => [
   'table'=>'vendors','label'=>'ผู้ขาย/ผู้รับจ้าง','perm'=>'budget.po','soft'=>false,
   'back'=>'po','defaults'=>['school_id'=>1],
   'fields'=>[
     ['name'=>'name','label'=>'ชื่อผู้ขาย/ผู้รับจ้าง','type'=>'text','required'=>true],
     ['name'=>'tax_id','label'=>'เลขผู้เสียภาษี','type'=>'text'],
     ['name'=>'address','label'=>'ที่อยู่','type'=>'text'],
     ['name'=>'phone','label'=>'เบอร์โทร','type'=>'text'],
     ['name'=>'email','label'=>'อีเมล','type'=>'text'],
     ['name'=>'contact_name','label'=>'ผู้ติดต่อ','type'=>'text'],
     ['name'=>'is_active','label'=>'ใช้งาน','type'=>'select','options'=>['1'=>'ใช้งาน','0'=>'ปิด']],
   ],
 ],


];
