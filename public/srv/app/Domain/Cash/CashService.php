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

    /** @return array<string,float> */
    public function totals(int $sessionId): array
    {
        $u=Auth::user(); $s=Database::connection()->prepare('SELECT opening_amount FROM cash_sessions WHERE id=:id AND company_id=:company AND branch_id=:branch');
        $s->execute(['id'=>$sessionId,'company'=>$u['company_id'],'branch'=>$u['branch_id']]); $opening=$s->fetchColumn();
        if($opening===false) throw new \RuntimeException('Sessão inválida.');
        $q=Database::connection()->prepare("SELECT COALESCE(SUM(CASE WHEN movement_type IN ('sale','supply') THEN amount ELSE -amount END),0) FROM cash_movements WHERE cash_session_id=:id");
        $q->execute(['id'=>$sessionId]); return ['opening'=>(float)$opening,'expected'=>round((float)$opening+(float)$q->fetchColumn(),2)];
    }

    public function close(string $counted,string $reason=''): void
    {
        $u=Auth::user(); $value=self::money($counted);
        Database::transactional(function(PDO $pdo) use($u,$value,$reason): void {
            $session=$this->current(); if(!$session) throw new \RuntimeException('Nenhum caixa aberto.');
            $totals=$this->totals((int)$session['id']); $difference=round((float)$value-$totals['expected'],2);
            if(abs($difference)>=0.01 && mb_strlen(trim($reason))<5) throw new \InvalidArgumentException('Justifique a divergência do fechamento.');
            $now=date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE cash_sessions SET status=\'closed\',expected_cash=:expected,counted_cash=:counted,difference_amount=:difference,closing_notes=:notes,closing_reason=:reason,closed_by=:user,closed_at=:at,open_guard=NULL WHERE id=:id AND status=\'open\'')
                ->execute(['expected'=>number_format($totals['expected'],2,'.',''),'counted'=>$value,'difference'=>number_format($difference,2,'.',''),'notes'=>trim($reason)?:null,'reason'=>trim($reason)?:null,'user'=>$u['id'],'at'=>$now,'id'=>$session['id']]);
            Audit::log('cash.closed','cash_sessions',$session['id'],null,['expected'=>$totals['expected'],'counted'=>$value,'difference'=>$difference,'reason'=>$reason]);
        });
    }

    /** @return array<int,array<string,mixed>> */
    public function history(): array
    {
        $u=Auth::user(); $s=Database::connection()->prepare('SELECT cs.*,cr.name register_name,u.name user_name FROM cash_sessions cs JOIN cash_registers cr ON cr.id=cs.cash_register_id JOIN users u ON u.id=cs.user_id WHERE cs.company_id=:company AND cs.branch_id=:branch ORDER BY cs.id DESC LIMIT 100');
        $s->execute(['company'=>$u['company_id'],'branch'=>$u['branch_id']]); return $s->fetchAll();
    }

    private static function money(mixed $v): string {
        $v=str_replace(',','.',trim((string)$v)); if(!preg_match('/^\d{1,13}(?:\.\d{1,2})?$/',$v)) throw new \InvalidArgumentException('Valor monetário inválido.');
        return number_format((float)$v,2,'.','');
    }
}
