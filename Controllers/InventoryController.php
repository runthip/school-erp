<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Catalog;

class InventoryController extends Controller
{
    public function index(): void
    {
        $this->authorize('inventory.manage');
        $c=new Catalog();
        $q=trim((string)Request::input('q','')); $cat=(string)Request::input('cat',''); $cond=(string)Request::input('cond','');
        $this->view('inventory/index',[
            'title'=>'ครุภัณฑ์และพัสดุ','assets'=>$c->assets($q,$cat,$cond),'materials'=>$c->materials(),
            'cats'=>$c->assetCategories(),'q'=>$q,'cat'=>$cat,'cond'=>$cond,
        ]);
    }
}
