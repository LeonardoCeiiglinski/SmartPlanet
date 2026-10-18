<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Domain\Purchasing\PurchaseService;
use App\Domain\Stock\StockService;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/bootstrap.php';

final class InventoryPurchaseTest extends TestCase
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
        $now = '2026-01-01 10:00:00';
        $this->pdo->exec("INSERT INTO companies (id,legal_name,trade_name,created_at,updated_at) VALUES (1,'C','C','{$now}','{$now}')");
        $this->pdo->exec("INSERT INTO branches (id,company_id,name,code,created_at,updated_at) VALUES (1,1,'Matriz','M','{$now}','{$now}')");
        $this->pdo->exec("INSERT INTO roles (id,company_id,name,created_at) VALUES (1,1,'Administrador','{$now}')");
        $hash = password_hash('x', PASSWORD_DEFAULT);
        $this->pdo->exec("INSERT INTO users (id,company_id,branch_id,role_id,name,email,password_hash,created_at,updated_at) VALUES (1,1,1,1,'Admin','a@a.test','{$hash}','{$now}','{$now}')");
        $this->pdo->exec("INSERT INTO units (id,company_id,code,name) VALUES (1,1,'UN','Unidade')");
        $this->pdo->exec("INSERT INTO suppliers (id,company_id,name,created_at,updated_at) VALUES (1,1,'Fornecedor','{$now}','{$now}')");
        $this->pdo->exec("INSERT INTO products (id,company_id,unit_id,internal_code,description,cost_price,sale_price,minimum_stock,created_at,updated_at) VALUES (1,1,1,'P1','Produto',5,10,2,'{$now}','{$now}')");
        $this->pdo->exec("INSERT INTO stock_locations (id,company_id,branch_id,code,name) VALUES (1,1,1,'LOJA','Loja')");
        Session::put('user_id', 1);
    }

    public function testAdjustmentIsScopedAndCreatesImmutableMovement(): void
    {
        $id = (new StockService())->adjust(1, 1, '-1,5', 'Contagem divergente');
        self::assertGreaterThan(0, $id);
        $row = $this->pdo->query('SELECT quantity,reason FROM stock_movements')->fetch();
        self::assertSame('-1.5', (string) (float) $row['quantity']);
        self::assertSame('Contagem divergente', $row['reason']);
    }

    public function testPartialReceiptIsIdempotentAndMovesStockOnce(): void
    {
        $service = new PurchaseService();
        $order = $service->create(1, [['product_id' => 1, 'quantity' => '10', 'unit_cost' => '7.50']]);
        $item = (int) $this->pdo->query('SELECT id FROM purchase_order_items')->fetchColumn();
        $first = $service->receive($order, 1, 'same-request', [$item => '4']);
        $second = $service->receive($order, 1, 'same-request', [$item => '4']);
        self::assertSame($first, $second);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM stock_movements WHERE source_type='purchase_receipt'")->fetchColumn());
        self::assertSame('4', (string) (float) $this->pdo->query('SELECT received_quantity FROM purchase_order_items')->fetchColumn());
        self::assertSame('partially_received', $this->pdo->query('SELECT status FROM purchase_orders')->fetchColumn());
        self::assertSame('7.5', (string) (float) $this->pdo->query('SELECT cost_price FROM products')->fetchColumn());
    }

    public function testInventoryClosingProducesOnlyDifferenceMovement(): void
    {
        $service = new StockService();
        $service->adjust(1, 1, '5', 'Carga de teste');
        $inventory = $service->createInventory(1);
        $service->closeInventory($inventory, [1 => '7']);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn());
        self::assertSame('2', (string) (float) $this->pdo->query("SELECT quantity FROM stock_movements WHERE source_type='inventory_count'")->fetchColumn());
        self::assertSame('closed', $this->pdo->query('SELECT status FROM inventory_counts')->fetchColumn());
    }
}
