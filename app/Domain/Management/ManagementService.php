<?php

declare(strict_types=1);

namespace App\Domain\Management;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Domain\Finance\FinanceLedger;
use App\Domain\Sales\PosService;
use PDO;

final class ManagementService
{
    /** @param array<int,array<string,mixed>> $items */
    public function createQuote(string $key, array $items, ?int $customerId, ?string $validUntil, string $notes = ''): int
    {
        $u = Auth::user(); $key = trim($key);
        if ($key === '' || !$items) throw new \InvalidArgumentException('Orçamento e chave idempotente são obrigatórios.');
        return Database::transactional(function (PDO $pdo) use ($u,$key,$items,$customerId,$validUntil,$notes): int {
            $q=$pdo->prepare('SELECT id FROM quotes WHERE company_id=:company AND idempotency_key=:key');
            $q->execute(['company'=>$u['company_id'],'key'=>$key]); if($id=$q->fetchColumn()) return (int)$id;
            if($customerId){$c=$pdo->prepare('SELECT 1 FROM customers WHERE id=:id AND company_id=:company AND active=1');$c->execute(['id'=>$customerId,'company'=>$u['company_id']]);if(!$c->fetchColumn())throw new \InvalidArgumentException('Cliente inválido.');}
            $safe=[];$subtotal=$discount=0.0;
            foreach($items as $item){
                $p=$pdo->prepare('SELECT id,description,sale_price FROM products WHERE id=:id AND company_id=:company AND active=1');
                $p->execute(['id'=>(int)($item['product_id']??0),'company'=>$u['company_id']]);$product=$p->fetch();
                $qty=self::decimal($item['quantity']??0,4);$disc=self::decimal($item['discount']??0,2);
                if(!$product||$qty<=0||$disc<0)throw new \InvalidArgumentException('Item de orçamento inválido.');
                $gross=round((float)$product['sale_price']*$qty,2);if($disc>$gross)throw new \InvalidArgumentException('Desconto excede o item.');
                $subtotal+=$gross;$discount+=$disc;$safe[]=[$product,$qty,$disc,$gross-$disc];
            }
            $now=date('Y-m-d H:i:s');$number='OR-'.date('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(2)));
            $pdo->prepare('INSERT INTO quotes(company_id,branch_id,customer_id,quote_number,status,subtotal,discount_amount,total,valid_until,created_by,created_at,updated_at,idempotency_key) VALUES(:company,:branch,:customer,:number,\'open\',:subtotal,:discount,:total,:valid,:user,:at,:at,:key)')
                ->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id'],'customer'=>$customerId?:null,'number'=>$number,'subtotal'=>self::money($subtotal),'discount'=>self::money($discount),'total'=>self::money($subtotal-$discount),'valid'=>$validUntil?:null,'user'=>$u['id'],'at'=>$now,'key'=>$key]);
            $id=(int)$pdo->lastInsertId();
            foreach($safe as [$p,$qty,$disc,$total])$pdo->prepare('INSERT INTO quote_items(quote_id,product_id,description,quantity,unit_price,discount_amount,subtotal)VALUES(:quote,:product,:description,:quantity,:price,:discount,:subtotal)')->execute(['quote'=>$id,'product'=>$p['id'],'description'=>$p['description'],'quantity'=>$qty,'price'=>self::money((float)$p['sale_price']),'discount'=>self::money($disc),'subtotal'=>self::money($total)]);
            Audit::log('quote.created','quotes',$id,null,['total'=>$subtotal-$discount,'notes'=>trim($notes)]);return $id;
        });
    }

    /** @param array<int,array<string,mixed>> $payments */
    public function convertQuote(int $quoteId,string $key,array $payments): int
    {
        $u=Auth::user();
        return Database::transactional(function(PDO $pdo) use($u,$quoteId,$key,$payments): int {
            $q=$pdo->prepare('SELECT * FROM quotes WHERE id=:id AND company_id=:company AND branch_id=:branch');
            $q->execute(['id'=>$quoteId,'company'=>$u['company_id'],'branch'=>$u['branch_id']]);$quote=$q->fetch();
            if(!$quote)throw new \RuntimeException('Orçamento não encontrado.');
            if($quote['converted_sale_id'])return (int)$quote['converted_sale_id'];
            if(!in_array($quote['status'],['open','approved'],true))throw new \RuntimeException('Orçamento não pode ser convertido.');
            if($quote['valid_until']&&$quote['valid_until']<date('Y-m-d'))throw new \RuntimeException('Orçamento vencido.');
            $i=$pdo->prepare('SELECT product_id,quantity,discount_amount discount FROM quote_items WHERE quote_id=:id');$i->execute(['id'=>$quoteId]);
            $sale=(new PosService())->complete($key,$i->fetchAll(),$payments,$quote['customer_id']?(int)$quote['customer_id']:null,'Conversão '.$quote['quote_number']);
            $update=$pdo->prepare('UPDATE quotes SET status=\'converted\',converted_sale_id=:sale,updated_at=:at WHERE id=:id AND converted_sale_id IS NULL');
            $update->execute(['sale'=>$sale,'at'=>date('Y-m-d H:i:s'),'id'=>$quoteId]);
            Audit::log('quote.converted','quotes',$quoteId,['status'=>$quote['status']],['sale_id'=>$sale]);return $sale;
        });
    }

    public function cancelQuote(int $quoteId, string $reason): void
    {
        $u = Auth::user();
        if (mb_strlen(trim($reason)) < 5) {
            throw new \InvalidArgumentException('Justificativa obrigatória.');
        }
        Database::transactional(function (PDO $pdo) use ($u, $quoteId, $reason): void {
            $q = $pdo->prepare('SELECT * FROM quotes WHERE id=:id AND company_id=:company AND branch_id=:branch');
            $q->execute(['id' => $quoteId, 'company' => $u['company_id'], 'branch' => $u['branch_id']]);
            $quote = $q->fetch();
            if (!$quote || $quote['converted_sale_id'] || $quote['status'] === 'converted') {
                throw new \RuntimeException('Orçamento convertido não pode ser cancelado.');
            }
            if ($quote['status'] === 'cancelled') {
                return;
            }
            $pdo->prepare('UPDATE quotes SET status=\'cancelled\',updated_at=:at WHERE id=:id')
                ->execute(['at' => date('Y-m-d H:i:s'), 'id' => $quoteId]);
            Audit::log('quote.cancelled', 'quotes', $quoteId, ['status' => $quote['status']], ['reason' => $reason]);
        });
    }

    /** @param array<int,array<string,mixed>> $items */
    public function returnSale(int $saleId,string $key,array $items,string $reason,bool $refundCash): int
    {
        Auth::authorize('sales.return');$u=Auth::user();
        if(trim($key)===''||mb_strlen(trim($reason))<5||!$items)throw new \InvalidArgumentException('Informe itens, motivo e chave da devolução.');
        return Database::transactional(function(PDO $pdo) use($u,$saleId,$key,$items,$reason,$refundCash): int {
            $e=$pdo->prepare('SELECT id FROM sale_returns WHERE company_id=:company AND idempotency_key=:key');$e->execute(['company'=>$u['company_id'],'key'=>$key]);if($id=$e->fetchColumn())return(int)$id;
            $s=$pdo->prepare('SELECT * FROM sales WHERE id=:id AND company_id=:company AND branch_id=:branch AND status=\'completed\'');$s->execute(['id'=>$saleId,'company'=>$u['company_id'],'branch'=>$u['branch_id']]);$sale=$s->fetch();if(!$sale)throw new \RuntimeException('Venda não disponível para devolução.');
            $safe=[];$total=0.0;
            foreach($items as $line){
                $q=self::decimal($line['quantity']??0,4);$st=$pdo->prepare('SELECT si.*,p.track_stock,p.cost_price FROM sale_items si JOIN products p ON p.id=si.product_id WHERE si.id=:id AND si.sale_id=:sale');$st->execute(['id'=>(int)($line['sale_item_id']??0),'sale'=>$saleId]);$item=$st->fetch();
                if(!$item||$q<=0||$q>(float)$item['quantity']-(float)$item['returned_quantity']+0.00001)throw new \RuntimeException('Quantidade devolvida excede o saldo vendido.');
                $amount=round(((float)$item['subtotal']/(float)$item['quantity'])*$q,2);$total+=$amount;$safe[]=[$item,$q,$amount,!empty($line['return_to_stock'])];
            }
            $now=date('Y-m-d H:i:s');$number='DV-'.date('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(2)));
            $pdo->prepare('INSERT INTO sale_returns(company_id,branch_id,sale_id,return_number,total,reason,status,created_by,authorized_by,created_at,idempotency_key)VALUES(:company,:branch,:sale,:number,:total,:reason,\'confirmed\',:user,:user,:at,:key)')->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id'],'sale'=>$saleId,'number'=>$number,'total'=>self::money($total),'reason'=>trim($reason),'user'=>$u['id'],'at'=>$now,'key'=>$key]);$returnId=(int)$pdo->lastInsertId();
            $loc=$pdo->prepare('SELECT id FROM stock_locations WHERE company_id=:company AND branch_id=:branch AND active=1 ORDER BY id LIMIT 1');$loc->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id']]);$location=(int)$loc->fetchColumn();
            foreach($safe as [$item,$qty,$amount,$toStock]){
                $pdo->prepare('INSERT INTO sale_return_items(sale_return_id,sale_item_id,quantity,amount,return_to_stock)VALUES(:return,:item,:quantity,:amount,:stock)')->execute(['return'=>$returnId,'item'=>$item['id'],'quantity'=>$qty,'amount'=>self::money($amount),'stock'=>$toStock?1:0]);
                $pdo->prepare('UPDATE sale_items SET returned_quantity=returned_quantity+:quantity WHERE id=:id')->execute(['quantity'=>$qty,'id'=>$item['id']]);
                if($toStock&&(int)$item['track_stock']===1)$pdo->prepare('INSERT INTO stock_movements(company_id,branch_id,location_id,product_id,movement_type,quantity,source_type,source_id,source_line,reason,user_id,occurred_at)VALUES(:company,:branch,:location,:product,\'sale_return\',:quantity,\'sale_return\',:source,:line,:reason,:user,:at)')->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id'],'location'=>$location,'product'=>$item['product_id'],'quantity'=>$qty,'source'=>(string)$returnId,'line'=>(string)$item['id'],'reason'=>$reason,'user'=>$u['id'],'at'=>$now]);
            }
            FinanceLedger::post($pdo,$u,['direction'=>'out','category'=>FinanceLedger::RETURNS,'description'=>'Estorno '.$sale['sale_number'],'document'=>$number,'amount'=>$total,'status'=>$refundCash?'settled':'open','source_type'=>'sale_return','source_id'=>(string)$returnId,'customer_id'=>$sale['customer_id']?:null]);
            $cogs=0.0; foreach($safe as [$item,$qty]) { $cogs += round((float)$item['cost_price']*$qty,2); }
            if($cogs>0){ FinanceLedger::post($pdo,$u,['direction'=>'in','category'=>FinanceLedger::COGS,'description'=>'Estorno CMV '.$sale['sale_number'],'document'=>$number,'amount'=>$cogs,'status'=>'settled','source_type'=>'sale_cogs_return','source_id'=>(string)$returnId]); }
            if($refundCash){$cs=$pdo->prepare('SELECT id FROM cash_sessions WHERE company_id=:company AND branch_id=:branch AND user_id=:user AND status=\'open\' ORDER BY id DESC LIMIT 1');$cs->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id'],'user'=>$u['id']]);$session=$cs->fetchColumn();if(!$session)throw new \RuntimeException('Abra o caixa para estorno em dinheiro.');$pdo->prepare('INSERT INTO cash_movements(cash_session_id,movement_type,amount,reason,source_type,source_id,user_id,authorized_by,created_at)VALUES(:session,\'return\',:amount,:reason,\'sale_return\',:source,:user,:user,:at)')->execute(['session'=>$session,'amount'=>self::money($total),'reason'=>$reason,'source'=>(string)$returnId,'user'=>$u['id'],'at'=>$now]);}
            Audit::log('sale.returned','sale_returns',$returnId,null,['sale_id'=>$saleId,'total'=>$total]);return$returnId;
        });
    }

    public function createEntry(string $direction,string $description,string $amount,string $issue,string $due,?int $partyId=null): int
    {
        Auth::authorize('finance.manage');$u=Auth::user();if(!in_array($direction,['in','out'],true)||trim($description)==='')throw new \InvalidArgumentException('Lançamento inválido.');$value=self::decimal($amount,2);if($value<=0)throw new \InvalidArgumentException('Valor inválido.');
        return Database::transactional(function(PDO $pdo) use($u,$direction,$description,$amount,$issue,$due,$partyId,$value): int {
            return FinanceLedger::post($pdo,$u,[
                'direction'=>$direction,
                'category'=>FinanceLedger::MANUAL,
                'description'=>trim($description),
                'amount'=>$value,
                'date'=>$issue,
                'due'=>$due,
                'status'=>'open',
                'source_type'=>'manual',
                'source_id'=>'manual-'.bin2hex(random_bytes(8)),
                'customer_id'=>$direction==='in'?$partyId:null,
                'supplier_id'=>$direction==='out'?$partyId:null,
            ]);
        });
    }

    public function cancelEntry(int $entryId, string $reason): void
    {
        Auth::authorize('finance.manage');
        $u = Auth::user();
        if (mb_strlen(trim($reason)) < 5) {
            throw new \InvalidArgumentException('Justificativa obrigatória.');
        }
        Database::transactional(function (PDO $pdo) use ($u, $entryId, $reason): void {
            $e = $pdo->prepare('SELECT * FROM financial_entries WHERE id=:id AND company_id=:company AND branch_id=:branch');
            $e->execute(['id' => $entryId, 'company' => $u['company_id'], 'branch' => $u['branch_id']]);
            $entry = $e->fetch();
            if (!$entry) {
                throw new \RuntimeException('Lançamento não encontrado.');
            }
            if ($entry['source_type'] !== 'manual') {
                throw new \RuntimeException('Cancele pela origem operacional (venda, compra, caixa ou estoque).');
            }
            if ((float) $entry['settled_amount'] > 0) {
                throw new \RuntimeException('Lançamento já liquidado não pode ser apagado. Preserve o histórico.');
            }
            $pdo->prepare('UPDATE financial_entries SET status=\'cancelled\',updated_at=:at WHERE id=:id')
                ->execute(['at' => date('Y-m-d H:i:s'), 'id' => $entryId]);
            Audit::log('finance.cancelled', 'financial_entries', $entryId, ['status' => $entry['status']], ['reason' => $reason]);
        });
    }

    public function settle(int $entryId,int $accountId,string $amount,string $key,string $notes=''): int
    {
        Auth::authorize('finance.manage');$u=Auth::user();$value=self::decimal($amount,2);if($value<=0||trim($key)==='')throw new \InvalidArgumentException('Liquidação inválida.');
        return Database::transactional(function(PDO $pdo)use($u,$entryId,$accountId,$value,$key,$notes):int{
            $e=$pdo->prepare('SELECT * FROM financial_entries WHERE id=:id AND company_id=:company AND branch_id=:branch');$e->execute(['id'=>$entryId,'company'=>$u['company_id'],'branch'=>$u['branch_id']]);$entry=$e->fetch();if(!$entry||in_array($entry['status'],['cancelled','settled'],true))throw new \RuntimeException('Conta não está aberta.');
            $old=$pdo->prepare('SELECT id FROM financial_settlements WHERE financial_entry_id=:entry AND idempotency_key=:key');$old->execute(['entry'=>$entryId,'key'=>$key]);if($id=$old->fetchColumn())return(int)$id;
            $a=$pdo->prepare('SELECT 1 FROM financial_accounts WHERE id=:id AND company_id=:company AND active=1 AND (branch_id IS NULL OR branch_id=:branch)');$a->execute(['id'=>$accountId,'company'=>$u['company_id'],'branch'=>$u['branch_id']]);if(!$a->fetchColumn())throw new \InvalidArgumentException('Conta financeira inválida.');
            $remaining=round((float)$entry['amount']-(float)$entry['settled_amount'],2);if($value>$remaining+0.001)throw new \RuntimeException('Liquidação excede o saldo da conta.');
            $pdo->prepare('INSERT INTO financial_settlements(financial_entry_id,financial_account_id,amount,settled_at,user_id,notes,idempotency_key)VALUES(:entry,:account,:amount,:at,:user,:notes,:key)')->execute(['entry'=>$entryId,'account'=>$accountId,'amount'=>self::money($value),'at'=>date('Y-m-d H:i:s'),'user'=>$u['id'],'notes'=>trim($notes)?:null,'key'=>$key]);$id=(int)$pdo->lastInsertId();$new=round((float)$entry['settled_amount']+$value,2);$status=$new+0.001>=(float)$entry['amount']?'settled':'partial';$pdo->prepare('UPDATE financial_entries SET settled_amount=:amount,status=:status,updated_at=:at WHERE id=:id')->execute(['amount'=>self::money($new),'status'=>$status,'at'=>date('Y-m-d H:i:s'),'id'=>$entryId]);Audit::log('finance.settled','financial_settlements',$id,null,['entry_id'=>$entryId,'amount'=>$value]);return$id;
        });
    }

    private static function decimal(mixed $value,int $scale):float{$value=str_replace(',','.',trim((string)$value));if(!preg_match('/^\d{1,13}(?:\.\d{1,4})?$/',$value))throw new \InvalidArgumentException('Valor numérico inválido.');return round((float)$value,$scale);}
    private static function money(float $value):string{return number_format($value,2,'.','');}
}
