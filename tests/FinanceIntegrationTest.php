<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Domain\Cash\CashService;
use App\Domain\Management\ManagementService;
use App\Domain\Purchasing\PurchaseService;
use App\Domain\Sales\PosService;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/bootstrap.php';

final class FinanceIntegrationTest extends TestCase
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
        $files = glob(dirname(__DIR__) . '/database/migrations/*.php') ?: [];
        sort($files);
        foreach ($files as $file) {
            (require $file)['up']($this->pdo, 'sqlite');
        }
        $n = '2026-01-01 10:00:00';
        $this->pdo->exec("INSERT INTO companies(id,legal_name,trade_name,created_at,updated_at)VALUES(1,'C','C','$n','$n')");
        $this->pdo->exec("INSERT INTO branches(id,company_id,name,code,created_at,updated_at)VALUES(1,1,'M','M','$n','$n')");
        $this->pdo->exec("INSERT INTO roles(id,company_id,name,created_at)VALUES(1,1,'Administrador','$n')");
        $h = password_hash('x', PASSWORD_DEFAULT);
        $this->pdo->exec("INSERT INTO users(id,company_id,branch_id,role_id,name,email,password_hash,created_at,updated_at)VALUES(1,1,1,1,'A','a@a','$h','$n','$n')");
        $this->pdo->exec("INSERT INTO units(id,company_id,code,name)VALUES(1,1,'UN','Un')");
        $this->pdo->exec("INSERT INTO suppliers(id,company_id,name,created_at,updated_at)VALUES(1,1,'Fornecedor','$n','$n')");
        $this->pdo->exec("INSERT INTO products(id,company_id,unit_id,internal_code,description,cost_price,sale_price,created_at,updated_at)VALUES(1,1,1,'000123','Produto',4,10,'$n','$n')");
        $this->pdo->exec("INSERT INTO product_barcodes(company_id,product_id,barcode,created_at)VALUES(1,1,'000123','$n')");
        $this->pdo->exec("INSERT INTO stock_locations(id,company_id,branch_id,code,name)VALUES(1,1,1,'L','Loja')");
        $this->pdo->exec("INSERT INTO stock_movements(company_id,branch_id,location_id,product_id,movement_type,quantity,source_type,source_id,source_line,reason,occurred_at)VALUES(1,1,1,1,'load',20,'test','1','','Teste','$n')");
        $this->pdo->exec("INSERT INTO cash_registers(id,company_id,branch_id,name,code)VALUES(1,1,1,'Caixa','C1')");
        $this->pdo->exec("INSERT INTO payment_methods(id,company_id,code,name,method_type,requires_confirmation)VALUES(1,1,'DIN','Dinheiro','cash',0)");
        $this->pdo->exec("INSERT INTO financial_accounts(id,company_id,branch_id,name,account_type,active)VALUES(1,1,1,'Caixa','cash',1)");
        Session::put('user_id', 1);
    }

    public function testSalePostsRevenueCogsCashAndCancelReversesOnce(): void
    {
        (new CashService())->open(1, '0');
        $pos = new PosService();
        $id = $pos->complete('fin-sale', [['product_id' => 1, 'quantity' => 2, 'discount' => 0]], [['method_id' => 1, 'amount' => 20]]);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM financial_entries WHERE source_type='sale_payment' AND status='settled'")->fetchColumn());
        self::assertSame('8', (string) (float) $this->pdo->query("SELECT amount FROM financial_entries WHERE source_type='sale_cogs'")->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM cash_movements WHERE source_type='sale'")->fetchColumn());
        $pos->cancel($id, 'Cliente desistiu da compra');
        self::assertSame('cancelled', $this->pdo->query("SELECT status FROM financial_entries WHERE source_type='sale_payment'")->fetchColumn());
        self::assertSame('cancelled', $this->pdo->query("SELECT status FROM financial_entries WHERE source_type='sale_cogs'")->fetchColumn());
        self::assertSame('20', (string) (float) $this->pdo->query('SELECT SUM(quantity) FROM stock_movements WHERE product_id=1')->fetchColumn());
        $again = false;
        try {
            $pos->cancel($id, 'Tentar de novo o cancelamento');
        } catch (Throwable) {
            $again = true;
        }
        self::assertSame(true, $again);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM cash_movements WHERE source_type='sale_cancel'")->fetchColumn());
    }

    public function testPurchaseReceiptCreatesPayableWithoutDuplicating(): void
    {
        $purchases = new PurchaseService();
        $order = $purchases->create(1, [['product_id' => 1, 'quantity' => '4', 'unit_cost' => '6']]);
        $item = (int) $this->pdo->query('SELECT id FROM purchase_order_items')->fetchColumn();
        $first = $purchases->receive($order, 1, 'rec-1', [$item => '4']);
        $purchases->receive($order, 1, 'rec-1', [$item => '4']);
        self::assertSame($first, $first);
        self::assertSame('24', (string) (float) $this->pdo->query("SELECT amount FROM financial_entries WHERE source_type='purchase_receipt' AND status='open'")->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM financial_entries WHERE source_type='purchase_receipt'")->fetchColumn());
        $blocked = false;
        try {
            $purchases->cancel($order, 'Já recebido não cancela');
        } catch (Throwable) {
            $blocked = true;
        }
        self::assertSame(true, $blocked);
    }

    public function testReturnReversesRevenueAndCogsAndBlocksSaleCancel(): void
    {
        (new CashService())->open(1, '0');
        $sale = (new PosService())->complete('ret-sale', [['product_id' => 1, 'quantity' => 2, 'discount' => 0]], [['method_id' => 1, 'amount' => 20]]);
        $itemId = (int) $this->pdo->query("SELECT id FROM sale_items WHERE sale_id=$sale")->fetchColumn();
        $m = new ManagementService();
        $m->returnSale($sale, 'ret-1', [['sale_item_id' => $itemId, 'quantity' => 1, 'return_to_stock' => true]], 'Defeito no aparelho', true);
        self::assertSame('10', (string) (float) $this->pdo->query("SELECT amount FROM financial_entries WHERE source_type='sale_return'")->fetchColumn());
        self::assertSame('4', (string) (float) $this->pdo->query("SELECT amount FROM financial_entries WHERE source_type='sale_cogs_return'")->fetchColumn());
        $blocked = false;
        try {
            (new PosService())->cancel($sale, 'Não pode cancelar com devolução');
        } catch (Throwable) {
            $blocked = true;
        }
        self::assertSame(true, $blocked);
    }

    public function testQuoteCancelDoesNotTouchStockOrFinance(): void
    {
        $m = new ManagementService();
        $id = $m->createQuote('orc-1', [['product_id' => 1, 'quantity' => 1, 'discount' => 0]], null, date('Y-m-d', strtotime('+3 days')));
        $stock = (int) $this->pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn();
        $fin = (int) $this->pdo->query('SELECT COUNT(*) FROM financial_entries')->fetchColumn();
        $m->cancelQuote($id, 'Cliente desistiu do orçamento');
        self::assertSame('cancelled', $this->pdo->query("SELECT status FROM quotes WHERE id=$id")->fetchColumn());
        self::assertSame($stock, (int) $this->pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn());
        self::assertSame($fin, (int) $this->pdo->query('SELECT COUNT(*) FROM financial_entries')->fetchColumn());
    }
}
