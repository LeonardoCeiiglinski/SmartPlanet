<?php

declare(strict_types=1);

namespace App\Domain\Cash;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Domain\Finance\FinanceLedger;
use PDO;

final class CashService
{
    /** @return array<string,mixed>|null */
    public function current(): ?array
    {
        $u=Auth::user();
        $s=Database::connection()->prepare('SELECT cs.*,cr.name AS register_name FROM cash_sessions cs JOIN cash_registers cr ON cr.id=cs.cash_register_id WHERE cs.company_id=:company AND cs.branch_id=:branch AND cs.user_id=:user AND cs.status=\'open\' ORDER BY cs.id DESC LIMIT 1');
        $s->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id'],'user'=>$u['id']]);
        $row=$s->fetch(); return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function takeoverAvailable(): ?array
    {
        $u = Auth::user();
        if (!$this->isCashOperator($u) || $this->current() !== null) {
            return null;
        }
        $s = Database::connection()->prepare(
            'SELECT cs.id, cs.opened_at, cs.opening_amount, cr.name AS register_name, u.name AS user_name
             FROM cash_sessions cs
             JOIN cash_registers cr ON cr.id = cs.cash_register_id
             JOIN users u ON u.id = cs.user_id
             WHERE cs.company_id = :company AND cs.branch_id = :branch AND cs.status = \'open\' AND cs.user_id <> :user
             ORDER BY cs.id DESC LIMIT 1'
        );
        $s->execute(['company' => $u['company_id'], 'branch' => $u['branch_id'], 'user' => $u['id']]);
        $row = $s->fetch();
        return $row ?: null;
    }

    public function assume(int $sessionId): void
    {
        $u = Auth::user();
        if (!$this->isCashOperator($u)) {
            throw new \RuntimeException('Só o operador de caixa pode assumir um caixa aberto.');
        }
        Database::transactional(function (PDO $pdo) use ($u, $sessionId): void {
            $own = $pdo->prepare('SELECT id FROM cash_sessions WHERE company_id=:company AND branch_id=:branch AND user_id=:user AND status=\'open\' LIMIT 1');
            $own->execute(['company' => $u['company_id'], 'branch' => $u['branch_id'], 'user' => $u['id']]);
            if ($own->fetchColumn()) {
                throw new \RuntimeException('Você já está com um caixa aberto.');
            }
            $s = $pdo->prepare(
                'SELECT cs.id, cs.user_id, cr.name AS register_name
                 FROM cash_sessions cs
                 JOIN cash_registers cr ON cr.id = cs.cash_register_id
                 WHERE cs.id = :id AND cs.company_id = :company AND cs.branch_id = :branch AND cs.status = \'open\''
            );
            $s->execute(['id' => $sessionId, 'company' => $u['company_id'], 'branch' => $u['branch_id']]);
            $row = $s->fetch();
            if (!$row) {
                throw new \RuntimeException('Não há caixa aberto para assumir.');
            }
            if ((int) $row['user_id'] === (int) $u['id']) {
                return;
            }
            $pdo->prepare('UPDATE cash_sessions SET user_id = :user WHERE id = :id AND status = \'open\'')
                ->execute(['user' => $u['id'], 'id' => $sessionId]);
            Audit::log('cash.assumed', 'cash_sessions', $sessionId, ['user_id' => (int) $row['user_id']], ['user_id' => (int) $u['id'], 'register' => $row['register_name']]);
        });
    }

    /** @param array<string,mixed> $user */
    private function isCashOperator(array $user): bool
    {
        return mb_strtolower((string) ($user['role_name'] ?? '')) === 'operador de caixa';
    }

    public function open(int $registerId, string $amount, string $notes=''): int
    {
        $u=Auth::user(); $value=self::money($amount);
        return Database::transactional(function(PDO $pdo) use($u,$registerId,$value,$notes): int {
            $r=$pdo->prepare('SELECT 1 FROM cash_registers WHERE id=:id AND company_id=:company AND branch_id=:branch AND active=1');
            $r->execute(['id'=>$registerId,'company'=>$u['company_id'],'branch'=>$u['branch_id']]);
            if(!$r->fetchColumn()) throw new \InvalidArgumentException('Caixa inválido.');
            $now=date('Y-m-d H:i:s');
            try {
                $pdo->prepare('INSERT INTO cash_sessions (company_id,branch_id,cash_register_id,user_id,status,opening_amount,opened_at,opening_notes,open_guard) VALUES (:company,:branch,:register,:user,\'open\',:amount,:at,:notes,\'open\')')
                    ->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id'],'register'=>$registerId,'user'=>$u['id'],'amount'=>$value,'at'=>$now,'notes'=>trim($notes)?:null]);
            } catch (\PDOException) { throw new \RuntimeException('Este caixa já possui uma sessão aberta.'); }
            $id=(int)$pdo->lastInsertId(); Audit::log('cash.opened','cash_sessions',$id,null,['opening_amount'=>$value]); return $id;
        });
    }

    public function move(string $type,string $amount,string $reason): int
    {
        if(!in_array($type,['supply','withdrawal'],true)) throw new \InvalidArgumentException('Movimento inválido.');
        $u=Auth::user(); $value=self::money($amount);
        if(mb_strlen(trim($reason))<5) throw new \InvalidArgumentException('Informe uma justificativa com ao menos 5 caracteres.');
        return Database::transactional(function(PDO $pdo) use($u,$type,$value,$reason): int {
            $session=$this->current(); if(!$session) throw new \RuntimeException('Nenhum caixa aberto.');
            $pdo->prepare('INSERT INTO cash_movements (cash_session_id,movement_type,amount,reason,source_type,source_id,user_id,created_at) VALUES (:session,:type,:amount,:reason,\'cash_move\',:source,:user,:at)')
                ->execute(['session'=>$session['id'],'type'=>$type,'amount'=>$value,'reason'=>trim($reason),'source'=>bin2hex(random_bytes(8)),'user'=>$u['id'],'at'=>date('Y-m-d H:i:s')]);
            $id=(int)$pdo->lastInsertId();
            FinanceLedger::post($pdo, $u, [
                'direction' => $type === 'supply' ? 'in' : 'out',
                'category' => $type === 'supply' ? FinanceLedger::CASH_IN : FinanceLedger::CASH_OUT,
                'description' => ($type === 'supply' ? 'Reforço de caixa' : 'Sangria de caixa').': '.trim($reason),
                'amount' => (float) $value,
                'status' => 'settled',
                'source_type' => 'cash_movement',
                'source_id' => (string) $id,
            ]);
            Audit::log('cash.'.$type,'cash_movements',$id,null,['amount'=>$value,'reason'=>$reason]); return $id;
        });
    }

    /**
     * Vendas gravadas nesta sessão, com a forma de pagamento.
     * PIX e cartão não entram no dinheiro esperado da gaveta.
     *
     * @return array{sales:array<int,array<string,mixed>>,by_method:array<int,array{name:string,type:string,total:float}>,count:int,total:float}
     */
    public function sessionSales(int $sessionId): array
    {
        $u = Auth::user();
        $pdo = Database::connection();
        $sales = $pdo->prepare(
            'SELECT id, sale_number, completed_at, total, status
             FROM sales
             WHERE cash_session_id = :id AND company_id = :company AND branch_id = :branch
             ORDER BY id DESC'
        );
        $sales->execute(['id' => $sessionId, 'company' => $u['company_id'], 'branch' => $u['branch_id']]);
        $rows = $sales->fetchAll();
        $payments = $pdo->prepare(
            'SELECT sp.sale_id, pm.name, pm.method_type, sp.amount
             FROM sale_payments sp
             JOIN payment_methods pm ON pm.id = sp.payment_method_id
             JOIN sales s ON s.id = sp.sale_id
             WHERE s.cash_session_id = :id AND s.company_id = :company AND s.branch_id = :branch
             ORDER BY sp.id'
        );
        $payments->execute(['id' => $sessionId, 'company' => $u['company_id'], 'branch' => $u['branch_id']]);
        $bySale = [];
        $byMethod = [];
        foreach ($payments->fetchAll() as $payment) {
            $saleId = (int) $payment['sale_id'];
            $bySale[$saleId][] = $payment['name'];
            $key = (string) $payment['name'];
            if (!isset($byMethod[$key])) {
                $byMethod[$key] = ['name' => $key, 'type' => (string) $payment['method_type'], 'total' => 0.0];
            }
            $byMethod[$key]['total'] += (float) $payment['amount'];
        }
        $total = 0.0;
        foreach ($rows as &$row) {
            $row['methods'] = isset($bySale[(int) $row['id']]) ? implode(', ', $bySale[(int) $row['id']]) : '';
            if ($row['status'] === 'completed') {
                $total += (float) $row['total'];
            }
        }
        unset($row);

        return [
            'sales' => $rows,
            'by_method' => array_values($byMethod),
            'count' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'completed')),
            'total' => round($total, 2),
        ];
    }

    /** @return array<string,float> */
    public function totals(int $sessionId): array
    {
        $u=Auth::user(); $s=Database::connection()->prepare('SELECT opening_amount FROM cash_sessions WHERE id=:id AND company_id=:company AND branch_id=:branch');
        $s->execute(['id'=>$sessionId,'company'=>$u['company_id'],'branch'=>$u['branch_id']]); $opening=$s->fetchColumn();
        if($opening===false) throw new \RuntimeException('Sessão inválida.');
        $q=Database::connection()->prepare("SELECT COALESCE(SUM(CASE WHEN movement_type IN ('sale','supply') THEN amount ELSE -amount END),0) FROM cash_movements WHERE cash_session_id=:id");
        $q->execute(['id'=>$sessionId]); return ['opening'=>(float)$opening,'expected'=>round((float)$opening+(float)$q->fetchColumn(),2)];
    }

    public function close(string $counted, string $reason = ''): int
    {
        $u = Auth::user();
        $value = self::money($counted);
        return (int) Database::transactional(function (PDO $pdo) use ($u, $value, $reason): int {
            $session = $this->current();
            if (!$session) {
                throw new \RuntimeException('Nenhum caixa aberto.');
            }
            $totals = $this->totals((int) $session['id']);
            $difference = round((float) $value - $totals['expected'], 2);
            if (abs($difference) >= 0.01 && mb_strlen(trim($reason)) < 5) {
                throw new \InvalidArgumentException('Justifique a divergência do fechamento.');
            }
            $now = date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE cash_sessions SET status=\'closed\',expected_cash=:expected,counted_cash=:counted,difference_amount=:difference,closing_notes=:notes,closing_reason=:reason,closed_by=:user,closed_at=:at,open_guard=NULL WHERE id=:id AND status=\'open\'')
                ->execute(['expected' => number_format($totals['expected'], 2, '.', ''), 'counted' => $value, 'difference' => number_format($difference, 2, '.', ''), 'notes' => trim($reason) ?: null, 'reason' => trim($reason) ?: null, 'user' => $u['id'], 'at' => $now, 'id' => $session['id']]);
            Audit::log('cash.closed', 'cash_sessions', $session['id'], null, ['expected' => $totals['expected'], 'counted' => $value, 'difference' => $difference, 'reason' => $reason]);

            return (int) $session['id'];
        });
    }

    /** @return array<string,mixed> */
    public function closingReport(int $sessionId): array
    {
        if ($sessionId <= 0) {
            throw new \InvalidArgumentException('Sessão inválida.');
        }
        $u = Auth::user();
        $pdo = Database::connection();
        $scope = ['id' => $sessionId, 'company' => $u['company_id'], 'branch' => $u['branch_id']];
        $session = $pdo->prepare(
            'SELECT cs.id, cs.status, cs.opening_amount, cs.opening_notes, cs.opened_at, cs.expected_cash, cs.counted_cash,
                    cs.difference_amount, cs.closing_notes, cs.closing_reason, cs.closed_at,
                    cr.name AS register_name, opener.name AS operator_name, closer.name AS closed_by_name
             FROM cash_sessions cs
             JOIN cash_registers cr ON cr.id = cs.cash_register_id
             JOIN users opener ON opener.id = cs.user_id
             LEFT JOIN users closer ON closer.id = cs.closed_by
             WHERE cs.id = :id AND cs.company_id = :company AND cs.branch_id = :branch'
        );
        $session->execute($scope);
        $row = $session->fetch();
        if (!$row) {
            throw new \RuntimeException('Sessão de caixa não encontrada.');
        }
        $expected = $row['expected_cash'] !== null
            ? (float) $row['expected_cash']
            : $this->totals($sessionId)['expected'];

        $company = $pdo->prepare('SELECT legal_name, trade_name, document, email, phone FROM companies WHERE id = :id');
        $company->execute(['id' => $u['company_id']]);
        $branch = $pdo->prepare('SELECT name, address_json FROM branches WHERE id = :id');
        $branch->execute(['id' => $u['branch_id']]);
        $branchRow = $branch->fetch() ?: [];

        $moves = $pdo->prepare(
            'SELECT m.movement_type, m.amount, m.reason, m.created_at, u.name AS user_name
             FROM cash_movements m JOIN users u ON u.id = m.user_id
             WHERE m.cash_session_id = :id ORDER BY m.id'
        );
        $moves->execute(['id' => $sessionId]);

        $sales = $pdo->prepare(
            'SELECT s.id, s.sale_number, s.completed_at, s.status, s.subtotal, s.discount_amount, s.total,
                    u.name AS operator_name, c.name AS customer_name
             FROM sales s
             JOIN users u ON u.id = s.operator_id
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE s.cash_session_id = :id AND s.company_id = :company AND s.branch_id = :branch
             ORDER BY s.id'
        );
        $sales->execute($scope);
        $saleRows = $sales->fetchAll();

        $payments = $pdo->prepare(
            'SELECT sp.sale_id, pm.name, pm.method_type, sp.amount, sp.installments, sp.reference
             FROM sale_payments sp
             JOIN payment_methods pm ON pm.id = sp.payment_method_id
             JOIN sales s ON s.id = sp.sale_id
             WHERE s.cash_session_id = :id AND s.company_id = :company
             ORDER BY sp.id'
        );
        $payments->execute(['id' => $sessionId, 'company' => $u['company_id']]);
        $payBySale = [];
        foreach ($payments->fetchAll() as $pay) {
            $payBySale[(int) $pay['sale_id']][] = $pay;
        }

        $items = $pdo->prepare(
            'SELECT s.id AS sale_id, si.description, si.barcode, si.quantity, si.unit_price, si.discount_amount, si.subtotal, si.returned_quantity,
                    p.internal_code
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             LEFT JOIN products p ON p.id = si.product_id
             WHERE s.cash_session_id = :id AND s.company_id = :company
             ORDER BY s.id, si.id'
        );
        $items->execute(['id' => $sessionId, 'company' => $u['company_id']]);
        $itemBySale = [];
        foreach ($items->fetchAll() as $item) {
            $itemBySale[(int) $item['sale_id']][] = $item;
        }

        $returns = $pdo->prepare(
            'SELECT r.return_number, r.created_at, r.total, r.reason, r.return_type, s.sale_number, u.name AS user_name
             FROM sale_returns r
             JOIN sales s ON s.id = r.sale_id
             JOIN users u ON u.id = r.created_by
             WHERE s.cash_session_id = :id AND s.company_id = :company
             ORDER BY r.id'
        );
        $returns->execute(['id' => $sessionId, 'company' => $u['company_id']]);

        foreach ($saleRows as &$sale) {
            $saleId = (int) $sale['id'];
            $sale['payments'] = $payBySale[$saleId] ?? [];
            $sale['items'] = $itemBySale[$saleId] ?? [];
        }
        unset($sale);

        return [
            'session' => $row,
            'expected' => $expected,
            'company' => $company->fetch() ?: [],
            'branch_name' => (string) ($branchRow['name'] ?? ''),
            'address' => \App\Domain\Management\QuoteDocument::addressLines($branchRow['address_json'] ?? null),
            'movements' => $moves->fetchAll(),
            'sales' => $saleRows,
            'returns' => $returns->fetchAll(),
            'printed_by' => (string) ($u['name'] ?? ''),
            'printed_at' => date('Y-m-d H:i:s'),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function history(): array
    {
        $u=Auth::user(); $s=Database::connection()->prepare('SELECT cs.*,cr.name register_name,u.name user_name FROM cash_sessions cs JOIN cash_registers cr ON cr.id=cs.cash_register_id JOIN users u ON u.id=cs.user_id WHERE cs.company_id=:company AND cs.branch_id=:branch ORDER BY cs.id DESC LIMIT 100');
        $s->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id']]);
        $rows = $s->fetchAll();
        foreach ($rows as &$row) {
            if (($row['status'] ?? '') === 'open' && $row['expected_cash'] === null) {
                $row['expected_cash'] = $this->totals((int) $row['id'])['expected'];
            }
        }
        unset($row);

        return $rows;
    }

    private static function money(mixed $v): string {
        $v=str_replace(',','.',trim((string)$v)); if(!preg_match('/^\d{1,13}(?:\.\d{1,2})?$/',$v)) throw new \InvalidArgumentException('Valor monetário inválido.');
        return number_format((float)$v,2,'.','');
    }
}
