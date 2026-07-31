<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Personnel;

class PersonnelController extends Controller
{
    public function index(): void
    {
        $this->authorize('hr.personnel');
        $m=new Personnel();
        $q=trim((string)Request::input('q','')); $page=max(1,(int)Request::input('page',1));
        $limit=20; $offset=($page-1)*$limit;
        $this->view('personnel/index',[
            'title'=>'ข้อมูลบุคลากร','rows'=>$m->paginate($q,$limit,$offset),
            'total'=>$m->countAll($q),'page'=>$page,'pages'=>max(1,(int)ceil($m->countAll($q)/$limit)),'q'=>$q,
        ]);
    }
    public function show(string $id): void
    {
        $this->authorize('hr.personnel');
        $m=new Personnel(); $p=$m->detail((int)$id);
        if(!$p) $this->back('personnel','error','ไม่พบบุคลากร');
        $hr=new \App\Models\Hr();
        $this->view('personnel/show',[
            'title'=>'ประวัติบุคลากร','p'=>$p,'teaching'=>$m->teaching((int)$id),'leaves'=>$m->leaves((int)$id),
            'leaveSummary'=>$hr->leaveSummary((int)$id),'salaryHistory'=>$hr->salaryHistory((int)$id),'paHistory'=>$hr->paForPerson((int)$id),
        ]);
    }
}
