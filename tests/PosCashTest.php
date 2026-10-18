<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Domain\Cash\CashService;
use App\Domain\Sales\PosService;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/bootstrap.php';

final class PosCashTest extends TestCase
{
    private PDO $pdo;
    protected function setUp(): void
    {
        Auth::reset(); $_SESSION=[]; $this->pdo=new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC); $this->pdo->exec('PRAGMA foreign_keys=ON'); Database::configure($this->pdo);
        foreach(glob(dirname(__DIR__).'/database/migrations/*.php')?:[] as $file)(require $file)['up']($this->pdo,'sqlite');
        $n='2026-01-01 10:00:00';
        $this->pdo->exec("INSERT INTO companies(id,legal_name,trade_name,created_at,updated_at)VALUES(1,'C','C','$n','$n')");
        $this->pdo->exec("INSERT INTO branches(id,company_id,name,code,created_at,updated_at)VALUES(1,1,'M','M','$n','$n')");
        $this->pdo->exec("INSERT INTO roles(id,company_id,name,created_at)VALUES(1,1,'Administrador','$n')");
        $h=password_hash('x',PASSWORD_DEFAULT);$this->pdo->exec("INSERT INTO users(id,company_id,branch_id,role_id,name,email,password_hash,created_at,updated_at)VALUES(1,1,1,1,'A','a@a','$h','$n','$n')");
        $this->pdo->exec("INSERT INTO users(id,company_id,branch_id,role_id,name,email,password_hash,created_at,updated_at)VALUES(2,1,1,1,'B','b@b','$h','$n','$n')");
        $this->pdo->exec("INSERT INTO units(id,company_id,code,name)VALUES(1,1,'UN','Un')");
        $this->pdo->exec("INSERT INTO products(id,company_id,unit_id,internal_code,description,sale_price,created_at,updated_at)VALUES(1,1,1,'000123','Produto',10,'$n','$n')");
        $this->pdo->exec("INSERT INTO product_barcodes(company_id,product_id,barcode,created_at)VALUES(1,1,'000123','$n')");
        $this->pdo->exec("INSERT INTO stock_locations(id,company_id,branch_id,code,name)VALUES(1,1,1,'L','Loja')");
        $this->pdo->exec("INSERT INTO stock_movements(company_id,branch_id,location_id,product_id,movement_type,quantity,source_type,source_id,source_line,reason,occurred_at)VALUES(1,1,1,1,'load',10,'test','1','','Teste','$n')");
        $this->pdo->exec("INSERT INTO cash_registers(id,company_id,branch_id,name,code)VALUES(1,1,1,'Caixa','C1')");
        $this->pdo->exec("INSERT INTO payment_methods(id,company_id,code,name,method_type,requires_confirmation)VALUES(1,1,'DIN','Dinheiro','cash',0),(2,1,'PIX','PIX','pix',1)");
        Session::put('user_id',1);
    }
    public function testSaleIsIdempotentAndStockMovesOnce(): void
    {
        (new CashService())->open(1,'50');
        $s=new PosService();$items=[['product_id'=>1,'quantity'=>2,'discount'=>0,'barcode'=>'000123']];$pay=[['method_id'=>1,'amount'=>20]];
        $a=$s->complete('same-key',$items,$pay);$b=$s->complete('same-key',$items,$pay);
        self::assertSame($a,$b);self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM stock_movements WHERE source_type='sale'")->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM financial_entries WHERE source_type='sale_payment'")->fetchColumn());
    }
    public function testCancelRestoresStockOnce(): void
    {
        (new CashService())->open(1,'0');$s=new PosService();$id=$s->complete('cancel-key',[['product_id'=>1,'quantity'=>1,'discount'=>0]],[['method_id'=>1,'amount'=>10]]);
        $s->cancel($id,'Erro operacional');
        self::assertSame('cancelled',$this->pdo->query("SELECT status FROM sales WHERE id=$id")->fetchColumn());
        self::assertSame('10',(string)(float)$this->pdo->query('SELECT SUM(quantity) FROM stock_movements WHERE product_id=1')->fetchColumn());
    }
    public function testClosingCalculatesCashAndDifference(): void
    {
        $c=new CashService();$id=$c->open(1,'100');$c->move('supply','20','Reforço operacional');$c->move('withdrawal','10','Retirada segura');$c->close('111','Diferença de contagem');
        $row=$this->pdo->query("SELECT expected_cash,difference_amount,status FROM cash_sessions WHERE id=$id")->fetch();
        self::assertSame('110',(string)(float)$row['expected_cash']);self::assertSame('1',(string)(float)$row['difference_amount']);self::assertSame('closed',$row['status']);
    }
    public function testSuspensionPersistsWithoutOperationalMovementsAndFinalizesOnce(): void
    {
        $service=new PosService();$beforeStock=(int)$this->pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn();
        $draft=$service->suspend('draft-key',[['product_id'=>1,'quantity'=>2,'discount'=>0,'barcode'=>'000123']],null,'Separar para cliente','Balcão 1');
        $this->pdo->exec("INSERT INTO suspended_sales(company_id,branch_id,operator_id,idempotency_key,payload_json,status,created_at,updated_at)VALUES(1,1,2,'other-key','{\"items\":[]}','open','2026-01-01 10:00:00','2026-01-01 10:00:00')");
        self::assertGreaterThan(0,$draft);
        self::assertSame(1,count($service->suspended()));
        self::assertSame($beforeStock,(int)$this->pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn());
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM financial_entries')->fetchColumn());
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM cash_movements')->fetchColumn());
        $recovered=$service->recover($draft);
        self::assertSame('000123',$recovered['items'][0]['barcode']);
        self::assertSame(2,$recovered['items'][0]['quantity']);
        (new CashService())->open(1,'0');
        $sale=$service->complete($recovered['idempotency_key'],$recovered['items'],[['method_id'=>1,'amount'=>20]],null,$recovered['notes'],$draft);
        self::assertGreaterThan(0,$sale);
        self::assertSame('completed',$this->pdo->query("SELECT status FROM suspended_sales WHERE id=$draft")->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM stock_movements WHERE source_type='sale'")->fetchColumn());
    }
}
