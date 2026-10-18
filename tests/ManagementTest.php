<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Domain\Cash\CashService;
use App\Domain\Management\ManagementService;
use App\Domain\Sales\PosService;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/bootstrap.php';

final class ManagementTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        Auth::reset();
        $_SESSION = [];
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys=ON');
        Database::configure($this->pdo);
        foreach (glob(dirname(__DIR__) . '/database/migrations/*.php') ?: [] as $file) {
            (require $file)['up']($this->pdo, 'sqlite');
        }
        $n = '2026-01-01 10:00:00';
        $this->pdo->exec("INSERT INTO companies(id,legal_name,trade_name,created_at,updated_at)VALUES(1,'C','C','$n','$n')");
        $this->pdo->exec("INSERT INTO branches(id,company_id,name,code,created_at,updated_at)VALUES(1,1,'M','M','$n','$n')");
        $this->pdo->exec("INSERT INTO roles(id,company_id,name,created_at)VALUES(1,1,'Administrador','$n')");
        $this->pdo->exec("INSERT INTO roles(id,company_id,name,created_at)VALUES(2,1,'Operador de caixa','$n')");
        $h = password_hash('x', PASSWORD_DEFAULT);
        $this->pdo->exec("INSERT INTO users(id,company_id,branch_id,role_id,name,email,password_hash,created_at,updated_at)VALUES(1,1,1,1,'A','a@a','$h','$n','$n')");
        $this->pdo->exec("INSERT INTO users(id,company_id,branch_id,role_id,name,email,password_hash,created_at,updated_at)VALUES(2,1,1,2,'Op','op@a','$h','$n','$n')");
        $this->pdo->exec("INSERT INTO units(id,company_id,code,name)VALUES(1,1,'UN','Un')");
        $this->pdo->exec("INSERT INTO products(id,company_id,unit_id,internal_code,description,sale_price,created_at,updated_at)VALUES(1,1,1,'000123','Produto',10,'$n','$n')");
        $this->pdo->exec("INSERT INTO product_barcodes(company_id,product_id,barcode,created_at)VALUES(1,1,'000123','$n')");
        $this->pdo->exec("INSERT INTO stock_locations(id,company_id,branch_id,code,name)VALUES(1,1,1,'L','Loja')");
        $this->pdo->exec("INSERT INTO stock_movements(company_id,branch_id,location_id,product_id,movement_type,quantity,source_type,source_id,source_line,reason,occurred_at)VALUES(1,1,1,1,'load',10,'test','1','','Teste','$n')");
        $this->pdo->exec("INSERT INTO cash_registers(id,company_id,branch_id,name,code)VALUES(1,1,1,'Caixa','C1')");
        $this->pdo->exec("INSERT INTO payment_methods(id,company_id,code,name,method_type,requires_confirmation)VALUES(1,1,'DIN','Dinheiro','cash',0)");
        $this->pdo->exec("INSERT INTO financial_accounts(id,company_id,branch_id,name,account_type,active)VALUES(1,1,1,'Caixa','cash',1)");
        Session::put('user_id', 1);
    }

    public function testQuoteIsIdempotentAndConvertsOnce(): void
    {
        $m = new ManagementService();
        $items = [['product_id' => 1, 'quantity' => 2, 'discount' => 0]];
        $a = $m->createQuote('q-1', $items, null, date('Y-m-d', strtotime('+7 days')));
        $b = $m->createQuote('q-1', $items, null, date('Y-m-d', strtotime('+7 days')));
        self::assertSame($a, $b);
        (new CashService())->open(1, '0');
        $sale = $m->convertQuote($a, 'sale-from-q', [['method_id' => 1, 'amount' => 20]]);
        $again = $m->convertQuote($a, 'sale-from-q-2', [['method_id' => 1, 'amount' => 20]]);
        self::assertSame($sale, $again);
        self::assertSame('converted', $this->pdo->query("SELECT status FROM quotes WHERE id=$a")->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM sales WHERE status='completed'")->fetchColumn());
    }

    public function testReturnCannotExceedSoldQuantityAndRestoresStock(): void
    {
        (new CashService())->open(1, '0');
        $sale = (new PosService())->complete('s1', [['product_id' => 1, 'quantity' => 2, 'discount' => 0]], [['method_id' => 1, 'amount' => 20]]);
        $itemId = (int) $this->pdo->query("SELECT id FROM sale_items WHERE sale_id=$sale")->fetchColumn();
        $m = new ManagementService();
        $return = $m->returnSale($sale, 'r1', [['sale_item_id' => $itemId, 'quantity' => 1, 'return_to_stock' => true]], 'Cliente desistiu', true);
        $again = $m->returnSale($sale, 'r1', [['sale_item_id' => $itemId, 'quantity' => 1, 'return_to_stock' => true]], 'Cliente desistiu', true);
        self::assertSame($return, $again);
        self::assertSame('9', (string) (float) $this->pdo->query('SELECT SUM(quantity) FROM stock_movements WHERE product_id=1')->fetchColumn());
        $failed = false;
        try {
            $m->returnSale($sale, 'r2', [['sale_item_id' => $itemId, 'quantity' => 2, 'return_to_stock' => true]], 'Tentativa acima do saldo', false);
        } catch (Throwable) {
            $failed = true;
        }
        self::assertSame(true, $failed);
    }

    public function testPartialSettlementAndOperatorCannotReturn(): void
    {
        $m = new ManagementService();
        $entry = $m->createEntry('in', 'Serviço avulso', '100,00', '2026-01-01', '2026-01-10');
        $first = $m->settle($entry, 1, '40', 'liq-1');
        $dup = $m->settle($entry, 1, '40', 'liq-1');
        self::assertSame($first, $dup);
        self::assertSame('partial', $this->pdo->query("SELECT status FROM financial_entries WHERE id=$entry")->fetchColumn());
        $m->settle($entry, 1, '60', 'liq-2');
        self::assertSame('settled', $this->pdo->query("SELECT status FROM financial_entries WHERE id=$entry")->fetchColumn());

        Auth::reset();
        Session::put('user_id', 2);
        $blocked = false;
        try {
            (new CashService())->open(1, '0');
            $sale = (new PosService())->complete('op-sale', [['product_id' => 1, 'quantity' => 1, 'discount' => 0]], [['method_id' => 1, 'amount' => 10]]);
            $itemId = (int) $this->pdo->query("SELECT id FROM sale_items WHERE sale_id=$sale")->fetchColumn();
            $m->returnSale($sale, 'op-r', [['sale_item_id' => $itemId, 'quantity' => 1, 'return_to_stock' => true]], 'Sem permissão', false);
        } catch (Throwable) {
            $blocked = true;
        }
        self::assertSame(true, $blocked);
    }
}
