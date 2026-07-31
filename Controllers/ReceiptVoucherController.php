<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\TravelClaim;

/**
 * คลังแบบฟอร์ม E-Office · ใบสำคัญรับเงิน (งป.08)
 * กรอก → พิมพ์ (ไม่เก็บลงฐานข้อมูล — เป็นแบบฟอร์มกรอกด่วนในคลัง)
 * สิทธิ์ admin.eoffice (เท่ากับคลังแบบฟอร์ม)
 */
class ReceiptVoucherController extends Controller
{
    private function guard(): void { $this->authorize('admin.eoffice'); }

    public function form(): void
    {
        $this->guard();
        $u = Auth::user();
        $this->view('receipt_voucher/form', [
            'title'    => 'ใบสำคัญรับเงิน (งป.08)',
            'meName'   => $u['full_name'] ?? '',
            'school'   => school_info(),
            'teachers' => (new TravelClaim())->teachers(),
        ]);
    }

    public function generate(): void
    {
        $this->guard();
        // POST = สร้างจากข้อมูลที่กรอก (ตรวจ CSRF) · GET = เปิด/รีเฟรชหน้าพิมพ์ (ฟอร์มเปล่าพิมพ์ได้ ไม่ 404)
        if (Request::isPost()) $this->verifyCsrf();

        // รวมรายการ (ตัดแถวว่างทิ้ง) + คำนวณยอดรวม
        $names = (array)Request::input('item_name', []);
        $amts  = (array)Request::input('item_amount', []);
        $items = []; $total = 0.0;
        foreach ($names as $i => $nm) {
            $nm = trim((string)$nm);
            $am = (float)($amts[$i] ?? 0);
            if ($nm === '' && $am == 0) continue;
            $items[] = ['name' => $nm, 'amount' => $am];
            $total += $am;
        }
        $total = round($total, 2);

        $s = fn($k)=>trim((string)Request::input($k, ''));
        $u = Auth::user();
        $data = [
            'voucher_no'  => $s('voucher_no'),
            'activity'    => $s('activity'),
            'doc_date'    => Request::input('doc_date', '') ?: null,
            'receiver'    => $s('receiver'),
            'address_no'  => $s('address_no'),
            'subdistrict' => $s('subdistrict'),
            'district'    => $s('district'),
            'province'    => $s('province'),
            'payer_from'  => $s('payer_from'),
            'payer'       => $s('payer'),
            'responsible' => $s('responsible'),
            'items'       => $items,
            'total'       => $total,
        ];
        $this->view('receipt_voucher/print', [
            'title'     => 'ใบสำคัญรับเงิน '.($data['voucher_no'] ?: ''),
            'd'         => $data,
            'school'    => school_info(),
            'receiverSignature' => $u['signature_path'] ?? null,
            'backUrl'   => 'receipt-voucher',
            'autoPrint' => true,
        ], 'print');
    }
}
