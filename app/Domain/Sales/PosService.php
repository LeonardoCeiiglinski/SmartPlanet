<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Domain\Finance\FinanceLedger;
use PDO;

final class PosService
{
    /** @return array<int,array<string,mixed>> */
    public function search(string $term, bool $exact = false): array
    {
        $u = Auth::user();
        $term = trim($term);
        if ($term === '' || strlen($term) > 100) {
            return [];
        }
        $match = $exact
            ? '(pb.barcode = :exact OR p.internal_code = :exact_code)'
            : '(pb.barcode = :exact OR p.internal_code = :exact_code OR LOWER(p.description) LIKE LOWER(:term))';
        $stmt = Database::connection()->prepare(
            'SELECT p.id,p.internal_code,p.description,p.sale_price,p.promotional_price,p.promotion_starts_at,p.promotion_ends_at,
                    p.track_stock,pb.barcode
             FROM products p LEFT JOIN product_barcodes pb ON pb.product_id=p.id
             WHERE p.company_id=:company AND p.active=1
               AND ' . $match . '
             ORDER BY CASE WHEN pb.barcode=:exact2 OR p.internal_code=:exact3 THEN 0 ELSE 1 END,p.description LIMIT 20'
        );
        $params = [
            'company' => $u['company_id'],
            'exact' => $term,
            'exact_code' => $term,
            'exact2' => $term,
            'exact3' => $term,
        ];
        if (!$exact) {
            $params['term'] = '%' . $term . '%';
        }
        $stmt->execute($params);
        return array_map(static function (array $p): array {
            $now = date('Y-m-d H:i:s');
            $promo = $p['promotional_price'] !== null
                && (!$p['promotion_starts_at'] || $p['promotion_starts_at'] <= $now)
                && (!$p['promotion_ends_at'] || $p['promotion_ends_at'] >= $now);
            $p['price'] = $promo ? $p['promotional_price'] : $p['sale_price'];
            return $p;
        }, $stmt->fetchAll());
    }

    /** @return array<int,array<string,mixed>> */
    public function catalog(): array
    {
        $u = Auth::user();
        $stmt = Database::connection()->prepare(
            'SELECT p.id, p.internal_code, p.description, p.sale_price, p.promotional_price,
                    p.promotion_starts_at, p.promotion_ends_at,
                    (SELECT pb.barcode FROM product_barcodes pb
                     WHERE pb.product_id = p.id
                     ORDER BY pb.is_primary DESC, pb.id
                     LIMIT 1) AS barcode
             FROM products p
             WHERE p.company_id = :company AND p.active = 1
             ORDER BY p.description'
        );
        $stmt->execute(['company' => $u['company_id']]);
        $now = date('Y-m-d H:i:s');

        return array_map(static function (array $p) use ($now): array {
            $promo = $p['promotional_price'] !== null
                && (!$p['promotion_starts_at'] || $p['promotion_starts_at'] <= $now)
                && (!$p['promotion_ends_at'] || $p['promotion_ends_at'] >= $now);
            $p['price'] = $promo ? $p['promotional_price'] : $p['sale_price'];

            return $p;
        }, $stmt->fetchAll());
    }

    /** @param array<int,array<string,mixed>> $items @param array<int,array<string,mixed>> $payments */
    public function complete(string $key, array $items, array $payments, ?int $customerId = null, string $notes = '', ?int $suspendedId = null): int
    {
        $u = Auth::user();
        $key = trim($key);
        if ($key === '' || strlen($key) > 100 || !$items || !$payments) {
            throw new \InvalidArgumentException('Venda, itens, pagamentos ou chave idempotente inválidos.');
        }
        return Database::transactional(function (PDO $pdo) use ($u, $key, $items, $payments, $customerId, $notes, $suspendedId): int {
            $existing = $pdo->prepare('SELECT id FROM sales WHERE company_id=:company AND idempotency_key=:key');
            $existing->execute(['company' => $u['company_id'], 'key' => $key]);
            if ($id = $existing->fetchColumn()) {
                return (int) $id;
            }
            $session = $this->openSession($pdo, $u);
            $locationId = $this->location($pdo, $u);
            $normalized = [];
            $subtotal = $discount = 0.0;
            foreach ($items as $line => $item) {
                $quantity = self::decimal($item['quantity'] ?? null, 4);
                if ($quantity <= 0) {
                    throw new \InvalidArgumentException('Quantidade ou desconto inválido.');
                }
                $stmt = $pdo->prepare('SELECT id,description,sale_price,promotional_price,promotion_starts_at,promotion_ends_at,track_stock,cost_price FROM products WHERE id=:id AND company_id=:company AND active=1');
                $stmt->execute(['id' => (int) ($item['product_id'] ?? 0), 'company' => $u['company_id']]);
                $product = $stmt->fetch();
                if (!$product) {
                    throw new \InvalidArgumentException('Produto inválido.');
                }
                $now = date('Y-m-d H:i:s');
                $promo = $product['promotional_price'] !== null && (!$product['promotion_starts_at'] || $product['promotion_starts_at'] <= $now) && (!$product['promotion_ends_at'] || $product['promotion_ends_at'] >= $now);
                $price = (float) ($promo ? $product['promotional_price'] : $product['sale_price']);
                $gross = round($price * $quantity, 2);
                $lineDiscount = self::lineDiscount($item, $gross);
                if ($lineDiscount > 0 && !Auth::can('sales.discount')) {
                    throw new \RuntimeException('Sem permissão para aplicar desconto.');
                }
                if ($lineDiscount > $gross) {
                    throw new \InvalidArgumentException('Desconto excede o item.');
                }
                if ((int) $product['track_stock'] === 1) {
                    $stock = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM stock_movements WHERE company_id=:company AND branch_id=:branch AND location_id=:location AND product_id=:product');
                    $stock->execute(['company' => $u['company_id'], 'branch' => $u['branch_id'], 'location' => $locationId, 'product' => $product['id']]);
                    if ((float) $stock->fetchColumn() < $quantity) {
                        throw new \RuntimeException('Estoque insuficiente para ' . $product['description'] . '.');
                    }
                }
                $subtotal += $gross;
                $discount += $lineDiscount;
                $normalized[] = [$line, $product, $quantity, $price, $lineDiscount, $gross - $lineDiscount, trim((string) ($item['barcode'] ?? '')) ?: null];
            }
            $total = round($subtotal - $discount, 2);
            $paid = 0.0;
            $normalizedPayments = [];
            foreach ($payments as $i => $payment) {
                $amount = self::decimal($payment['amount'] ?? null, 2);
                $method = $pdo->prepare('SELECT * FROM payment_methods WHERE id=:id AND company_id=:company AND active=1');
                $method->execute(['id' => (int) ($payment['method_id'] ?? 0), 'company' => $u['company_id']]);
                $row = $method->fetch();
                if (!$row || $amount <= 0) {
                    throw new \InvalidArgumentException('Pagamento inválido.');
                }
                $confirmed = !empty($payment['confirmed']);
                if ($row['method_type'] !== 'cash' && (int) $row['requires_confirmation'] === 1 && !$confirmed) {
                    throw new \RuntimeException('Confirme operacionalmente todos os pagamentos não-dinheiro.');
                }
                $installments = max(1, (int) ($payment['installments'] ?? 1));
                if ($installments > (int) $row['max_installments']) {
                    throw new \InvalidArgumentException('Parcelamento não permitido.');
                }
                $paid += $amount;
                $normalizedPayments[] = [$i, $row, $amount, $installments, $confirmed, trim((string) ($payment['reference'] ?? ''))];
            }
            if ($paid + 0.001 < $total || ($paid - $total > 0.001 && !array_filter($normalizedPayments, static fn($p) => $p[1]['method_type'] === 'cash'))) {
                throw new \InvalidArgumentException('Pagamentos não correspondem ao total; troco só é permitido em dinheiro.');
            }
            $now = date('Y-m-d H:i:s');
            $number = 'VD-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $pdo->prepare('INSERT INTO sales (company_id,branch_id,cash_session_id,customer_id,operator_id,sale_number,status,subtotal,discount_amount,total,idempotency_key,notes,completed_at,created_at,updated_at) VALUES (:company,:branch,:session,:customer,:operator,:number,\'completed\',:subtotal,:discount,:total,:key,:notes,:at,:at,:at)')
                ->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id'],'session'=>$session['id'],'customer'=>$customerId ?: null,'operator'=>$u['id'],'number'=>$number,'subtotal'=>self::money($subtotal),'discount'=>self::money($discount),'total'=>self::money($total),'key'=>$key,'notes'=>trim($notes) ?: null,'at'=>$now]);
            $saleId = (int) $pdo->lastInsertId();
            $cogs = 0.0;
            foreach ($normalized as [$line,$product,$qty,$price,$disc,$lineTotal,$barcode]) {
                $pdo->prepare('INSERT INTO sale_items (sale_id,product_id,barcode,description,quantity,unit_price,discount_amount,subtotal) VALUES (:sale,:product,:barcode,:description,:quantity,:price,:discount,:subtotal)')
                    ->execute(['sale'=>$saleId,'product'=>$product['id'],'barcode'=>$barcode,'description'=>$product['description'],'quantity'=>$qty,'price'=>self::money($price),'discount'=>self::money($disc),'subtotal'=>self::money($lineTotal)]);
                if ((int) $product['track_stock'] === 1) {
                    $pdo->prepare('INSERT INTO stock_movements (company_id,branch_id,location_id,product_id,movement_type,quantity,unit_cost,source_type,source_id,source_line,reason,user_id,occurred_at) VALUES (:company,:branch,:location,:product,\'sale\',:quantity,:cost,\'sale\',:source,:line,:reason,:user,:at)')
                        ->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id'],'location'=>$locationId,'product'=>$product['id'],'quantity'=>-$qty,'cost'=>self::money((float)$product['cost_price']),'source'=>(string)$saleId,'line'=>(string)$line,'reason'=>'Venda '.$number,'user'=>$u['id'],'at'=>$now]);
                }
                $cogs += round((float) $product['cost_price'] * $qty, 2);
            }
            $remaining = $total;
            foreach ($normalizedPayments as [$i,$method,$amount,$installments,$confirmed,$reference]) {
                $applied = min($amount, $remaining);
                $remaining = round($remaining - $applied, 2);
                $ref = $reference !== '' ? $reference : 'PAY-' . $i;
                $pdo->prepare('INSERT INTO sale_payments (sale_id,payment_method_id,amount,installments,status,operational_confirmation,reference,created_at) VALUES (:sale,:method,:amount,:installments,\'confirmed\',:confirmed,:reference,:at)')
                    ->execute(['sale'=>$saleId,'method'=>$method['id'],'amount'=>self::money($applied),'installments'=>$installments,'confirmed'=>$confirmed ? 1 : 0,'reference'=>$ref,'at'=>$now]);
                FinanceLedger::post($pdo, $u, [
                    'direction' => 'in',
                    'category' => FinanceLedger::SALES,
                    'description' => 'Venda '.$number.' - '.$method['name'],
                    'document' => $number,
                    'amount' => $applied,
                    'status' => 'settled',
                    'source_type' => 'sale_payment',
                    'source_id' => $saleId.'-'.$i,
                    'installment_count' => $installments,
                    'customer_id' => $customerId ?: null,
                ]);
                if ($method['method_type'] === 'cash') {
                    $pdo->prepare('INSERT INTO cash_movements (cash_session_id,movement_type,amount,reason,source_type,source_id,user_id,created_at) VALUES (:session,\'sale\',:amount,:reason,\'sale\',:source,:user,:at)')
                        ->execute(['session'=>$session['id'],'amount'=>self::money($applied),'reason'=>'Venda '.$number,'source'=>(string)$saleId,'user'=>$u['id'],'at'=>$now]);
                }
            }
            if ($cogs > 0) {
                FinanceLedger::post($pdo, $u, [
                    'direction' => 'out',
                    'category' => FinanceLedger::COGS,
                    'description' => 'CMV venda '.$number,
                    'document' => $number,
                    'amount' => $cogs,
                    'status' => 'settled',
                    'source_type' => 'sale_cogs',
                    'source_id' => (string) $saleId,
                ]);
            }
            if ($suspendedId !== null) {
                $draft = $pdo->prepare('UPDATE suspended_sales SET status=\'completed\',completed_sale_id=:sale,updated_at=:at WHERE id=:id AND company_id=:company AND branch_id=:branch AND operator_id=:operator AND status=\'open\'');
                $draft->execute(['sale'=>$saleId,'at'=>$now,'id'=>$suspendedId,'company'=>$u['company_id'],'branch'=>$u['branch_id'],'operator'=>$u['id']]);
                if ($draft->rowCount() !== 1) {
                    throw new \RuntimeException('Venda suspensa inválida ou já finalizada.');
                }
            }
            Audit::log('sale.completed', 'sales', $saleId, null, ['total'=>$total,'change'=>max(0,round($paid-$total,2))]);
            return $saleId;
        });
    }

    /** @param array<int,array<string,mixed>> $items */
    public function suspend(string $key, array $items, ?int $customerId = null, string $notes = '', string $label = ''): int
    {
        $u=Auth::user(); $key=trim($key);
        if($key===''||strlen($key)>100||!$items) throw new \InvalidArgumentException('Venda suspensa inválida.');
        return Database::transactional(function(PDO $pdo) use($u,$key,$items,$customerId,$notes,$label): int {
            $existing=$pdo->prepare('SELECT id FROM suspended_sales WHERE company_id=:company AND idempotency_key=:key');
            $existing->execute(['company'=>$u['company_id'],'key'=>$key]); $existingId=$existing->fetchColumn();
            if($customerId){
                $customer=$pdo->prepare('SELECT 1 FROM customers WHERE id=:id AND company_id=:company AND active=1');
                $customer->execute(['id'=>$customerId,'company'=>$u['company_id']]); if(!$customer->fetchColumn()) throw new \InvalidArgumentException('Cliente inválido.');
            }
            $safe=[];
            foreach($items as $item){
                $qty=self::decimal($item['quantity']??null,4);
                if($qty<=0) throw new \InvalidArgumentException('Item suspenso inválido.');
                $p=$pdo->prepare('SELECT id,description,sale_price,promotional_price,promotion_starts_at,promotion_ends_at FROM products WHERE id=:id AND company_id=:company AND active=1');
                $p->execute(['id'=>(int)($item['product_id']??0),'company'=>$u['company_id']]); $product=$p->fetch();
                if(!$product) throw new \InvalidArgumentException('Produto inválido.');
                $now=date('Y-m-d H:i:s'); $promo=$product['promotional_price']!==null&&(!$product['promotion_starts_at']||$product['promotion_starts_at']<=$now)&&(!$product['promotion_ends_at']||$product['promotion_ends_at']>=$now);
                $price=(float)($promo?$product['promotional_price']:$product['sale_price']);
                $gross=round($price*$qty,2);
                $discount=self::lineDiscount($item,$gross);
                if($discount>0&&!Auth::can('sales.discount')) throw new \RuntimeException('Sem permissão para aplicar desconto.');
                if($discount>$gross) throw new \InvalidArgumentException('Desconto excede o item.');
                $mode=($item['discount_mode']??'money')==='percent'?'percent':'money';
                $percent=$mode==='percent'?self::decimal($item['discount_percent']??0,2):0.0;
                $safe[]=['product_id'=>(int)$product['id'],'description'=>$product['description'],'price'=>$price,'quantity'=>$qty,'discount'=>$discount,'discount_mode'=>$mode,'discount_percent'=>$percent,'barcode'=>trim((string)($item['barcode']??''))];
            }
            $now=date('Y-m-d H:i:s'); $payload=json_encode(['items'=>$safe,'customer_id'=>$customerId?:null,'notes'=>trim($notes)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            if($existingId){
                $update=$pdo->prepare('UPDATE suspended_sales SET customer_id=:customer,label=:label,payload_json=:payload,updated_at=:at WHERE id=:id AND company_id=:company AND branch_id=:branch AND operator_id=:operator AND status=\'open\'');
                $update->execute(['customer'=>$customerId?:null,'label'=>trim($label)?:null,'payload'=>$payload,'at'=>$now,'id'=>$existingId,'company'=>$u['company_id'],'branch'=>$u['branch_id'],'operator'=>$u['id']]);
                if($update->rowCount()!==1) throw new \RuntimeException('Venda suspensa pertence a outro contexto ou já foi finalizada.');
                Audit::log('sale.suspended_updated','suspended_sales',(int)$existingId,null,['items'=>count($safe)]); return (int)$existingId;
            }
            $pdo->prepare('INSERT INTO suspended_sales (company_id,branch_id,operator_id,customer_id,idempotency_key,label,payload_json,status,created_at,updated_at) VALUES (:company,:branch,:operator,:customer,:key,:label,:payload,\'open\',:at,:at)')
                ->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id'],'operator'=>$u['id'],'customer'=>$customerId?:null,'key'=>$key,'label'=>trim($label)?:null,'payload'=>$payload,'at'=>$now]);
            $id=(int)$pdo->lastInsertId(); Audit::log('sale.suspended','suspended_sales',$id,null,['items'=>count($safe)]); return $id;
        });
    }

    /** @return array<int,array<string,mixed>> */
    public function suspended(): array
    {
        $u=Auth::user(); $s=Database::connection()->prepare('SELECT id,label,payload_json,created_at,updated_at FROM suspended_sales WHERE company_id=:company AND branch_id=:branch AND operator_id=:operator AND status=\'open\' ORDER BY updated_at DESC');
        $s->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id'],'operator'=>$u['id']]);
        return array_map(static function(array $row): array {$p=json_decode($row['payload_json'],true,64,JSON_THROW_ON_ERROR);$row['item_count']=count($p['items']??[]);$row['total']=array_reduce($p['items']??[],static fn(float $sum,array $i):float=>$sum+(float)$i['quantity']*(float)$i['price']-(float)$i['discount'],0.0);unset($row['payload_json']);return $row;},$s->fetchAll());
    }

    /** @return array<string,mixed> */
    public function recover(int $id): array
    {
        $u=Auth::user(); $s=Database::connection()->prepare('SELECT id,idempotency_key,payload_json FROM suspended_sales WHERE id=:id AND company_id=:company AND branch_id=:branch AND operator_id=:operator AND status=\'open\'');
        $s->execute(['id'=>$id,'company'=>$u['company_id'],'branch'=>$u['branch_id'],'operator'=>$u['id']]); $row=$s->fetch();
        if(!$row) throw new \RuntimeException('Venda suspensa não encontrada.');
        $payload=json_decode($row['payload_json'],true,64,JSON_THROW_ON_ERROR); $payload['suspended_id']=(int)$row['id']; $payload['idempotency_key']=$row['idempotency_key'];
        Audit::log('sale.suspended_recovered','suspended_sales',$id); return $payload;
    }

    public function cancel(int $saleId, string $reason): void
    {
        Auth::authorize('sales.cancel');
        $u = Auth::user();
        if (mb_strlen(trim($reason)) < 5) throw new \InvalidArgumentException('Justificativa obrigatória.');
        Database::transactional(function (PDO $pdo) use ($u,$saleId,$reason): void {
            $stmt=$pdo->prepare('SELECT * FROM sales WHERE id=:id AND company_id=:company AND branch_id=:branch');
            $stmt->execute(['id'=>$saleId,'company'=>$u['company_id'],'branch'=>$u['branch_id']]); $sale=$stmt->fetch();
            if (!$sale || $sale['status'] !== 'completed') throw new \RuntimeException('Venda não pode ser cancelada.');
            $returns=$pdo->prepare('SELECT COUNT(*) FROM sale_returns WHERE sale_id=:sale AND status=\'confirmed\'');
            $returns->execute(['sale'=>$saleId]);
            if ((int)$returns->fetchColumn() > 0) {
                throw new \RuntimeException('Esta venda possui devolução. Use o estorno já registrado; não cancele o cupom.');
            }
            $dup=$pdo->prepare('SELECT COUNT(*) FROM cash_movements WHERE source_type=\'sale_cancel\' AND source_id=:source');
            $dup->execute(['source'=>(string)$saleId]);
            if ((int)$dup->fetchColumn() > 0) {
                throw new \RuntimeException('Estorno de caixa desta venda já existe.');
            }
            $location=$this->location($pdo,$u); $now=date('Y-m-d H:i:s');
            $items=$pdo->prepare('SELECT si.*,p.track_stock FROM sale_items si JOIN products p ON p.id=si.product_id WHERE si.sale_id=:sale');
            $items->execute(['sale'=>$saleId]);
            foreach($items->fetchAll() as $item) if((int)$item['track_stock']===1) {
                $pdo->prepare('INSERT INTO stock_movements (company_id,branch_id,location_id,product_id,movement_type,quantity,source_type,source_id,source_line,reason,user_id,occurred_at) VALUES (:company,:branch,:location,:product,\'sale_cancel\',:quantity,\'sale_cancel\',:source,:line,:reason,:user,:at)')
                    ->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id'],'location'=>$location,'product'=>$item['product_id'],'quantity'=>$item['quantity'],'source'=>(string)$saleId,'line'=>(string)$item['id'],'reason'=>$reason,'user'=>$u['id'],'at'=>$now]);
            }
            FinanceLedger::cancelBySourcePrefix($pdo, (int)$u['company_id'], 'sale_payment', $saleId.'-');
            FinanceLedger::cancelBySource($pdo, (int)$u['company_id'], 'sale_cogs', (string)$saleId);
            $cashTarget = $this->cashSessionForReversal($pdo, $u, (int)$sale['cash_session_id']);
            $orig=$pdo->prepare('SELECT amount FROM cash_movements WHERE source_type=\'sale\' AND source_id=:source');
            $orig->execute(['source'=>(string)$saleId]);
            foreach ($orig->fetchAll() as $move) {
                $pdo->prepare('INSERT INTO cash_movements (cash_session_id,movement_type,amount,reason,source_type,source_id,user_id,authorized_by,created_at) VALUES (:session,\'sale_cancel\',:amount,:reason,\'sale_cancel\',:source,:user,:user,:at)')
                    ->execute(['session'=>$cashTarget,'amount'=>$move['amount'],'reason'=>$reason,'source'=>(string)$saleId,'user'=>$u['id'],'at'=>$now]);
            }
            $pdo->prepare('UPDATE sales SET status=\'cancelled\',cancelled_at=:at,cancelled_by=:user,cancellation_reason=:reason,updated_at=:at WHERE id=:id')->execute(['at'=>$now,'user'=>$u['id'],'reason'=>trim($reason),'id'=>$saleId]);
            Audit::log('sale.cancelled','sales',$saleId,['status'=>'completed'],['status'=>'cancelled','reason'=>$reason]);
        });
    }

    private function cashSessionForReversal(PDO $pdo, array $u, int $originalId): int
    {
        $stmt=$pdo->prepare('SELECT id,status FROM cash_sessions WHERE id=:id AND company_id=:company');
        $stmt->execute(['id'=>$originalId,'company'=>$u['company_id']]);
        $row=$stmt->fetch();
        if ($row && $row['status']==='open') {
            return (int)$row['id'];
        }
        $open=$this->openSession($pdo,$u);
        return (int)$open['id'];
    }

    /** @return array<string,mixed> */
    private function openSession(PDO $pdo, array $u): array
    {
        $stmt=$pdo->prepare('SELECT * FROM cash_sessions WHERE company_id=:company AND branch_id=:branch AND user_id=:user AND status=\'open\' ORDER BY id DESC LIMIT 1');
        $stmt->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id'],'user'=>$u['id']]);
        $row=$stmt->fetch(); if(!$row) throw new \RuntimeException('Abra o caixa antes de vender.'); return $row;
    }
    private function location(PDO $pdo,array $u): int {
        $stmt=$pdo->prepare('SELECT id FROM stock_locations WHERE company_id=:company AND branch_id=:branch AND active=1 ORDER BY id LIMIT 1');
        $stmt->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id']]); $id=$stmt->fetchColumn();
        if(!$id) throw new \RuntimeException('Configure um local de estoque.'); return (int)$id;
    }
    /** @param array<string,mixed> $item */
    private static function lineDiscount(array $item, float $gross): float
    {
        $mode = ($item['discount_mode'] ?? 'money') === 'percent' ? 'percent' : 'money';
        if ($mode === 'percent') {
            $percent = self::decimal($item['discount_percent'] ?? 0, 2);
            if ($percent < 0 || $percent > 100) {
                throw new \InvalidArgumentException('Percentual de desconto inválido.');
            }
            return round($gross * $percent / 100, 2);
        }
        $amount = self::decimal($item['discount'] ?? 0, 2);
        if ($amount < 0) {
            throw new \InvalidArgumentException('Quantidade ou desconto inválido.');
        }
        return $amount;
    }

    private static function decimal(mixed $v,int $scale): float {
        $v=str_replace(',','.',trim((string)$v)); if(!preg_match('/^-?\d{1,13}(?:\.\d{1,4})?$/',$v)) throw new \InvalidArgumentException('Valor numérico inválido.');
        return round((float)$v,$scale);
    }
    private static function money(float $v): string { return number_format($v,2,'.',''); }
}
