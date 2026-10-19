<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Domain\Sales\PosService;

final class PosController
{
    public function __construct(private readonly PosService $service = new PosService()) {}

    public function index(): void
    {
        Auth::authorize('pos.sell');
        $u=Auth::user(); $pdo=Database::connection();
        $methods=$pdo->prepare('SELECT id,name,method_type,requires_confirmation,max_installments FROM payment_methods WHERE company_id=:company AND active=1 ORDER BY id');
        $methods->execute(['company'=>$u['company_id']]);
        $customers=$pdo->prepare('SELECT id,name FROM customers WHERE company_id=:company AND active=1 ORDER BY name LIMIT 500');
        $customers->execute(['company'=>$u['company_id']]);
        $recovered=Session::get('_pos_recovered');
        Session::forget('_pos_recovered');
        View::render('pos/index',['title'=>'PDV','methods'=>$methods->fetchAll(),'customers'=>$customers->fetchAll(),'canDiscount'=>Auth::can('sales.discount'),'suspended'=>$this->service->suspended(),'recovered'=>is_array($recovered)?$recovered:null]);
    }

    public function search(): void
    {
        Auth::authorize('pos.sell');
        header('Content-Type: application/json; charset=UTF-8');
        $exact = (string) ($_GET['exact'] ?? '') === '1';
        echo json_encode(['items'=>$this->service->search((string)($_GET['q']??''), $exact)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }

    public function complete(): void
    {
        Auth::authorize('pos.sell'); Csrf::enforce();
        try {
            $payload=json_decode((string)($_POST['payload']??''),true,64,JSON_THROW_ON_ERROR);
            $id=$this->service->complete((string)($payload['idempotency_key']??''),(array)($payload['items']??[]),(array)($payload['payments']??[]),(int)($payload['customer_id']??0),(string)($payload['notes']??''),isset($payload['suspended_id'])?(int)$payload['suspended_id']:null);
            View::redirect('/sales?receipt='.$id,'sucesso','Venda concluída.');
        } catch(\Throwable $e) { Session::flash('erro',$e->getMessage()); View::redirect('/pos'); }
    }

    public function suspend(): void
    {
        Auth::authorize('pos.sell'); Csrf::enforce();
        try {
            $payload=json_decode((string)($_POST['payload']??''),true,64,JSON_THROW_ON_ERROR);
            $this->service->suspend((string)($payload['idempotency_key']??''),(array)($payload['items']??[]),(int)($payload['customer_id']??0),(string)($payload['notes']??''),(string)($_POST['label']??''));
            View::redirect('/pos','sucesso','Venda suspensa com segurança.');
        } catch(\Throwable $e){Session::flash('erro',$e->getMessage());View::redirect('/pos');}
    }

    public function recover(): void
    {
        Auth::authorize('pos.sell'); Csrf::enforce();
        try {
            Session::put('_pos_recovered',$this->service->recover((int)($_POST['suspended_id']??0)));
            View::redirect('/pos','sucesso','Venda recuperada.');
        } catch(\Throwable $e){Session::flash('erro',$e->getMessage());View::redirect('/pos');}
    }

    public function cancel(): void
    {
        Auth::authorize('sales.cancel'); Csrf::enforce();
        try { $this->service->cancel((int)($_POST['sale_id']??0),(string)($_POST['reason']??'')); View::redirect('/sales','sucesso','Venda cancelada e estoque recomposto.'); }
        catch(\Throwable $e){ Session::flash('erro',$e->getMessage()); View::redirect('/sales'); }
    }
}
