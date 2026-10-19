<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Domain\Purchasing\PurchaseService;

final class PurchaseController
{
    public function __construct(private readonly PurchaseService $service = new PurchaseService())
    {
    }

    public function index(): void
    {
        Auth::authorize('purchases.manage');
        $order = isset($_GET['id']) ? $this->service->find((int) $_GET['id']) : null;
        View::render('purchases/index', [
            'title' => 'Compras e recebimentos', 'orders' => $this->service->orders(), 'order' => $order,
            ...$this->options(),
        ]);
    }

    public function create(): void
    {
        Auth::authorize('purchases.manage');
        Csrf::enforce();
        try {
            $items = [];
            foreach ((array) ($_POST['products'] ?? []) as $i => $product) {
                if ((int) $product > 0) {
                    $items[] = ['product_id' => $product, 'quantity' => $_POST['quantities'][$i] ?? '',
                        'unit_cost' => $_POST['costs'][$i] ?? ''];
                }
            }
            $id = $this->service->create((int) ($_POST['supplier_id'] ?? 0), $items,
                (string) ($_POST['expected_at'] ?? ''), (string) ($_POST['notes'] ?? ''));
            View::redirect('/purchases?id=' . $id, 'sucesso', 'Pedido de compra criado.');
        } catch (\Throwable $exception) {
            Session::flash('erro', $exception->getMessage());
            View::redirect('/purchases');
        }
    }

    public function receive(): void
    {
        Auth::authorize('purchases.manage');
        Csrf::enforce();
        $order = (int) ($_POST['order_id'] ?? 0);
        try {
            $this->service->receive($order, (int) ($_POST['location_id'] ?? 0),
                (string) ($_POST['idempotency_key'] ?? ''), (array) ($_POST['quantities'] ?? []),
                (string) ($_POST['notes'] ?? ''));
            View::redirect('/purchases?id=' . $order, 'sucesso', 'Recebimento processado. Contas a pagar geradas no financeiro.');
        } catch (\Throwable $exception) {
            Session::flash('erro', $exception->getMessage());
            View::redirect('/purchases?id=' . $order);
        }
    }

    public function cancel(): void
    {
        Auth::authorize('purchases.manage');
        Csrf::enforce();
        $order = (int) ($_POST['order_id'] ?? 0);
        try {
            $this->service->cancel($order, (string) ($_POST['reason'] ?? ''));
            View::redirect('/purchases?id=' . $order, 'sucesso', 'Pedido cancelado sem movimento de estoque.');
        } catch (\Throwable $exception) {
            Session::flash('erro', $exception->getMessage());
            View::redirect('/purchases?id=' . $order);
        }
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    private function options(): array
    {
        $user = Auth::user();
        $pdo = Database::connection();
        $queries = [
            'suppliers' => 'SELECT id,name FROM suppliers WHERE company_id=:company AND active=1 ORDER BY name',
            'products' => 'SELECT id,internal_code,description,cost_price FROM products WHERE company_id=:company AND active=1 AND track_stock=1 ORDER BY description',
            'locations' => 'SELECT id,name FROM stock_locations WHERE company_id=:company AND branch_id=:branch AND active=1 ORDER BY name',
        ];
        $result = [];
        foreach ($queries as $key => $sql) {
            $stmt = $pdo->prepare($sql);
            $params = ['company' => $user['company_id']];
            if (str_contains($sql, ':branch')) {
                $params['branch'] = $user['branch_id'];
            }
            $stmt->execute($params);
            $result[$key] = $stmt->fetchAll();
        }
        return $result;
    }
}
