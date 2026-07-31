<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Catalog;

class BudgetController extends Controller
{
    public function index(): void
    {
        $this->authorize('budget.manage');
        $q=trim((string)Request::input('q','')); $status=(string)Request::input('status','');
        $c=new Catalog();
        $this->view('budget/index',['title'=>'งบประมาณและโครงการ','budgets'=>$c->budgets(),'projects'=>$c->projects($q,$status),'q'=>$q,'status'=>$status]);
    }
}
