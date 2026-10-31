<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Domain\Cash\CashCloseDocument;
use App\Domain\Cash\CashService;

final class CashController
{
    public function __construct(private readonly CashService $service = new CashService()) {}
    public function index(): void {
        Auth::authorize('cash.open'); $u=Auth::user(); $s=Database::connection()->prepare('SELECT id,name FROM cash_registers WHERE company_id=:company AND branch_id=:branch AND active=1 ORDER BY name');
        $s->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id']]); $current=$this->service->current();
        View::render('cash/index',['title'=>'Caixa','registers'=>$s->fetchAll(),'current'=>$current,'takeover'=>$current?null:$this->service->takeoverAvailable(),'totals'=>$current?$this->service->totals((int)$current['id']):null,'sessionSales'=>$current?$this->service->sessionSales((int)$current['id']):null,'history'=>$this->service->history()]);
    }
    public function open(): void { Auth::authorize('cash.open'); $this->run(fn()=>$this->service->open((int)($_POST['register_id']??0),(string)($_POST['amount']??''),(string)($_POST['notes']??'')),'Caixa aberto.'); }
    public function assume(): void { Auth::authorize('cash.open'); $this->run(fn()=>$this->service->assume((int)($_POST['session_id']??0)),'Caixa assumido. As vendas desta sessão continuam neste caixa.'); }
    public function move(): void { Auth::authorize('cash.move'); $this->run(fn()=>$this->service->move((string)($_POST['type']??''),(string)($_POST['amount']??''),(string)($_POST['reason']??'')),'Movimento registrado.'); }
    public function close(): void
    {
        Auth::authorize('cash.close');
        Csrf::enforce();
        try {
            $id = $this->service->close((string) ($_POST['counted'] ?? ''), (string) ($_POST['reason'] ?? ''));
            View::redirect('/cash/pdf?id=' . $id);
        } catch (\Throwable $e) {
            Session::flash('erro', $e->getMessage());
            View::redirect('/cash');
        }
    }

    public function pdf(): void
    {
        Auth::authorize('cash.open');
        try {
            $report = $this->service->closingReport((int) ($_GET['id'] ?? 0));
        } catch (\Throwable $e) {
            Session::flash('erro', $e->getMessage());
            View::redirect('/cash');
        }
        $id = (int) ($report['session']['id'] ?? 0);
        $binary = CashCloseDocument::render($report);
        $name = 'fechamento-caixa-' . $id . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $name . '"');
        header('Content-Length: ' . strlen($binary));
        header('Cache-Control: private, no-store');
        echo $binary;
    }
    private function run(callable $fn,string $message): void { Csrf::enforce(); try{$fn(); View::redirect('/cash','sucesso',$message);}catch(\Throwable $e){Session::flash('erro',$e->getMessage());View::redirect('/cash');} }
}
