<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Report;
use App\Models\BudgetLedger;

class ReportController extends Controller
{
    private Report $m;
    public function __construct(){ $this->m = new Report(); }

    // ================= รายงานผลการดำเนินงาน (ภาพรวมทุกฝ่าย) =================
    public function admin(): void
    {
        $this->authorize('admin.reports');
        $y=$this->yearInput();
        $this->view('reports/admin', ['title'=>'รายงานผลการดำเนินงาน',
            'd'=>$this->m->summary($y),'yearBe'=>$y,'years'=>$this->m->years()]);
    }
    public function adminPrint(): void
    {
        $this->authorize('admin.reports');
        $y=$this->yearInput();
        $this->view('reports/admin_print', ['title'=>'รายงานผลการดำเนินงาน',
            'd'=>$this->m->summary($y),'yearBe'=>$y,'backUrl'=>'reports','autoPrint'=>true],'print');
    }
    private function yearInput(): int
    {
        $y=(int)Request::input('year',0);
        if($y<2500||$y>2700){
            $cur=$this->m->years();
            foreach($cur as $r){ if((int)$r['is_current']){ $y=(int)$r['year_be']; break; } }
            if($y<2500) $y=(int)date('Y')+543;
        }
        return $y;
    }

    // ================= รายงานงบประมาณ (บนสมุดบัญชีคุมงบ) =================
    public function budget(): void
    {
        $this->authorize('budget.report');
        [$f,$from,$to,$label]=$this->budgetRange();
        $L=new BudgetLedger();
        $this->view('reports/budget', ['title'=>'รายงานงบประมาณ','f'=>$f,
            'from'=>$from,'to'=>$to,'label'=>$label,
            'totals'=>$L->reportTotals($from,$to),
            'byBudget'=>$L->reportByBudget($from,$to),
            'byType'=>$L->reportByType($from,$to),
            'byDept'=>$L->reportByDept($from,$to),
            'byProject'=>$L->reportByProject($from,$to),
            'monthly'=>$L->reportMonthly($from,$to),
            'years'=>$this->m->years()]);
    }
    public function budgetPrint(): void
    {
        $this->authorize('budget.report');
        [$f,$from,$to,$label]=$this->budgetRange();
        $L=new BudgetLedger();
        $this->view('reports/budget_print', ['title'=>'รายงานผลการใช้จ่ายงบประมาณ',
            'f'=>$f,'from'=>$from,'to'=>$to,'label'=>$label,
            'totals'=>$L->reportTotals($from,$to),
            'byBudget'=>$L->reportByBudget($from,$to),
            'byType'=>$L->reportByType($from,$to),
            'byDept'=>$L->reportByDept($from,$to),
            'byProject'=>$L->reportByProject($from,$to),
            'backUrl'=>'reports/budget','autoPrint'=>true],'print');
    }
    /** @return array{0:array,1:string,2:string,3:string} */
    private function budgetRange(): array
    {
        $mode=Request::input('mode','year');
        if(!in_array($mode,['month','quarter','year','custom'],true)) $mode='year';
        $yearBe=(int)Request::input('year',0);
        if($yearBe<2500||$yearBe>2700) $yearBe=(int)date('Y')+543;
        $part=(int)Request::input('part', $mode==='month'?(int)date('n'):1);
        $cf=(string)Request::input('from','');
        $ct=(string)Request::input('to','');
        [$from,$to,$label]=BudgetLedger::range($mode,$yearBe,$part,$cf,$ct);
        return [['mode'=>$mode,'year'=>$yearBe,'part'=>$part,'from'=>$cf,'to'=>$ct],$from,$to,$label];
    }
}
