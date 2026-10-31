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
        $s->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id']]); $receipt=null; $company=null; $address=[];
        if(isset($_GET['receipt'])) {
            $q=$pdo->prepare('SELECT s.*,u.name operator_name,c.name customer_name FROM sales s JOIN users u ON u.id=s.operator_id LEFT JOIN customers c ON c.id=s.customer_id WHERE s.id=:id AND s.company_id=:company AND s.branch_id=:branch');
            $q->execute(['id'=>(int)$_GET['receipt'],'company'=>$u['company_id'],'branch'=>$u['branch_id']]); $receipt=$q->fetch()?:null;
            if($receipt){$i=$pdo->prepare('SELECT * FROM sale_items WHERE sale_id=:id');$i->execute(['id'=>$receipt['id']]);$receipt['items']=$i->fetchAll();$p=$pdo->prepare('SELECT sp.*,pm.name method_name FROM sale_payments sp JOIN payment_methods pm ON pm.id=sp.payment_method_id WHERE sp.sale_id=:id');$p->execute(['id'=>$receipt['id']]);$receipt['payments']=$p->fetchAll();}
            $c=$pdo->prepare('SELECT trade_name,phone FROM companies WHERE id=:id');
            $c->execute(['id'=>$u['company_id']]); $company=$c->fetch()?:null;
            $b=$pdo->prepare('SELECT address_json FROM branches WHERE id=:id AND company_id=:company');
            $b->execute(['id'=>$u['branch_id'],'company'=>$u['company_id']]);
            $address=self::addressLines($b->fetchColumn());
        }
        View::render('sales/index',['title'=>'Vendas','sales'=>$s->fetchAll(),'receipt'=>$receipt,'company'=>$company,'receiptAddress'=>$address]);
    }

    /** @return list<string> */
    private static function addressLines(mixed $json): array
    {
        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
        } elseif (is_array($json)) {
            $decoded = $json;
        } else {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }
        $lines = [];
        foreach (['line', 'street', 'address', 'logradouro'] as $key) {
            if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                $lines[] = $decoded[$key];
            }
        }
        $city = trim(implode(' ', array_filter([
            is_string($decoded['district'] ?? null) ? $decoded['district'] : (is_string($decoded['bairro'] ?? null) ? $decoded['bairro'] : ''),
            is_string($decoded['city'] ?? null) ? $decoded['city'] : (is_string($decoded['cidade'] ?? null) ? $decoded['cidade'] : ''),
            is_string($decoded['state'] ?? null) ? $decoded['state'] : (is_string($decoded['uf'] ?? null) ? $decoded['uf'] : ''),
        ], static fn ($part) => $part !== '')));
        if ($city !== '') {
            $lines[] = $city;
        }
        $zip = $decoded['zip'] ?? $decoded['cep'] ?? '';
        if (is_string($zip) && $zip !== '') {
            $lines[] = $zip;
        }
        if ($lines === [] && array_is_list($decoded)) {
            foreach ($decoded as $line) {
                if (is_string($line) && trim($line) !== '') {
                    $lines[] = trim($line);
                }
            }
        }
        return $lines;
    }
}
