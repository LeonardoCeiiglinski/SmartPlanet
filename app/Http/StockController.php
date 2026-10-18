<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Domain\Stock\StockService;

final class StockController
{
    public function __construct(private readonly StockService $service = new StockService())
    {
    }

    public function index(): void
    {
        Auth::authorize('stock.view');
        $query = trim((string) ($_GET['q'] ?? ''));
        View::render('stock/index', [
            'title' => 'Estoque', 'overview' => $this->service->overview($query),
            'history' => $this->service->history((int) ($_GET['product'] ?? 0) ?: null),
            'locations' => $this->locations(), 'inventories' => $this->inventories(), 'query' => $query,
        ]);
    }

    public function adjust(): void
    {
        Auth::authorize('stock.adjust');
        Csrf::enforce();
        try {
            $this->service->adjust((int) ($_POST['product_id'] ?? 0), (int) ($_POST['location_id'] ?? 0),
                (string) ($_POST['quantity'] ?? ''), (string) ($_POST['reason'] ?? ''));
            View::redirect('/stock', 'sucesso', 'Ajuste registrado de forma imutável.');
        } catch (\Throwable $exception) {
            Session::flash('erro', $exception->getMessage());
            View::redirect('/stock');
        }
    }

    public function inventoryStart(): void
    {
        Auth::authorize('stock.adjust');
        Csrf::enforce();
        try {
            $id = $this->service->createInventory((int) ($_POST['location_id'] ?? 0), (string) ($_POST['notes'] ?? ''));
            View::redirect('/stock?inventory=' . $id, 'sucesso', 'Inventário iniciado.');
        } catch (\Throwable $exception) {
            Session::flash('erro', $exception->getMessage());
            View::redirect('/stock');
        }
    }

    public function inventoryClose(): void
    {
        Auth::authorize('stock.adjust');
        Csrf::enforce();
        try {
            $this->service->closeInventory((int) ($_POST['inventory_id'] ?? 0), (array) ($_POST['counts'] ?? []));
            View::redirect('/stock', 'sucesso', 'Inventário fechado e diferenças movimentadas.');
        } catch (\Throwable $exception) {
            Session::flash('erro', $exception->getMessage());
            View::redirect('/stock?inventory=' . (int) ($_POST['inventory_id'] ?? 0));
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function locations(): array
    {
        $user = Auth::user();
        $stmt = Database::connection()->prepare('SELECT id,name FROM stock_locations WHERE company_id=:company AND branch_id=:branch AND active=1 ORDER BY name');
        $stmt->execute(['company' => $user['company_id'], 'branch' => $user['branch_id']]);
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    private function inventories(): array
    {
        $user = Auth::user();
        $stmt = Database::connection()->prepare(
            'SELECT ic.*,l.name AS location_name FROM inventory_counts ic JOIN stock_locations l ON l.id=ic.location_id
             WHERE ic.company_id=:company AND ic.branch_id=:branch ORDER BY ic.started_at DESC LIMIT 30'
        );
        $stmt->execute(['company' => $user['company_id'], 'branch' => $user['branch_id']]);
        $rows = $stmt->fetchAll();
        $selected = (int) ($_GET['inventory'] ?? 0);
        foreach ($rows as &$row) {
            $row['items'] = [];
            if ((int) $row['id'] === $selected && $row['status'] === 'open') {
                $items = Database::connection()->prepare(
                    'SELECT ici.*,p.description FROM inventory_count_items ici JOIN products p ON p.id=ici.product_id
                     WHERE ici.inventory_count_id=:id ORDER BY p.description'
                );
                $items->execute(['id' => $row['id']]);
                $row['items'] = $items->fetchAll();
            }
        }
        return $rows;
    }
}
