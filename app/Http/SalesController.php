<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

final class SalesController
{
    public function index(): void
    {
        Auth::authorize('sales.view'); $u=Auth::user(); $pdo=Database::connection();
        $s=$pdo->prepare('SELECT s.*,u.name operator_name,c.name customer_name FROM sales s JOIN users u ON u.id=s.operator_id LEFT JOIN customers c ON c.id=s.customer_id WHERE s.company_id=:company AND s.branch_id=:branch ORDER BY s.id DESC LIMIT 200');
        $s->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id']]); $receipt=null;
        if(isset($_GET['receipt'])) {
            $q=$pdo->prepare('SELECT s.*,u.name operator_name,c.name customer_name FROM sales s JOIN users u ON u.id=s.operator_id LEFT JOIN customers c ON c.id=s.customer_id WHERE s.id=:id AND s.company_id=:company AND s.branch_id=:branch');
            $q->execute(['id'=>(int)$_GET['receipt'],'company'=>$u['company_id'],'branch'=>$u['branch_id']]); $receipt=$q->fetch()?:null;
            if($receipt){$i=$pdo->prepare('SELECT * FROM sale_items WHERE sale_id=:id');$i->execute(['id'=>$receipt['id']]);$receipt['items']=$i->fetchAll();$p=$pdo->prepare('SELECT sp.*,pm.name method_name FROM sale_payments sp JOIN payment_methods pm ON pm.id=sp.payment_method_id WHERE sp.sale_id=:id');$p->execute(['id'=>$receipt['id']]);$receipt['payments']=$p->fetchAll();}
        }
        View::render('sales/index',['title'=>'Vendas','sales'=>$s->fetchAll(),'receipt'=>$receipt]);
    }
}
