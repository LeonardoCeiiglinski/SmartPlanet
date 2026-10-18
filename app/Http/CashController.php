<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Domain\Cash\CashService;

final class CashController
{
    public function __construct(private readonly CashService $service = new CashService()) {}
    public function index(): void {
        Auth::authorize('cash.open'); $u=Auth::user(); $s=Database::connection()->prepare('SELECT id,name FROM cash_registers WHERE company_id=:company AND branch_id=:branch AND active=1 ORDER BY name');
        $s->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id']]); $current=$this->service->current();
        View::render('cash/index',['title'=>'Caixa','registers'=>$s->fetchAll(),'current'=>$current,'totals'=>$current?$this->service->totals((int)$current['id']):null,'history'=>$this->service->history()]);
    }
    public function open(): void { Auth::authorize('cash.open'); $this->run(fn()=>$this->service->open((int)($_POST['register_id']??0),(string)($_POST['amount']??''),(string)($_POST['notes']??'')),'Caixa aberto.'); }
    public function move(): void { Auth::authorize('cash.move'); $this->run(fn()=>$this->service->move((string)($_POST['type']??''),(string)($_POST['amount']??''),(string)($_POST['reason']??'')),'Movimento registrado.'); }
    public function close(): void { Auth::authorize('cash.close'); $this->run(fn()=>$this->service->close((string)($_POST['counted']??''),(string)($_POST['reason']??'')),'Caixa fechado.'); }
    private function run(callable $fn,string $message): void { Csrf::enforce(); try{$fn(); View::redirect('/cash','sucesso',$message);}catch(\Throwable $e){Session::flash('erro',$e->getMessage());View::redirect('/cash');} }
}
