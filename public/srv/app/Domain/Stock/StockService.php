<?php

declare(strict_types=1);

namespace App\Domain\Stock;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Domain\Finance\FinanceLedger;
use PDO;

final class StockService
{
    /** @return array<string,mixed> */
    public function overview(string $query = ''): array
    {
        $user = Auth::user();
        $params = ['company' => $user['company_id'], 'branch' => $user['branch_id']];
        $filter = '';
        if ($query !== '') {
            $like = '%' . mb_strtolower($query) . '%';
            $filter = ' AND (LOWER(p.description) LIKE :query OR LOWER(p.internal_code) LIKE :query_code
                OR EXISTS (SELECT 1 FROM product_barcodes pb WHERE pb.product_id = p.id AND pb.company_id = p.company_id AND pb.barcode LIKE :barcode))';
            $params['query'] = $like;
            $params['query_code'] = $like;
            $params['barcode'] = '%' . $query . '%';
        }
        $stmt = Database::connection()->prepare(
            "SELECT p.id,p.internal_code,p.description,p.minimum_stock,u.code AS unit_code,
                    COALESCE(SUM(sm.quantity),0) AS quantity
             FROM products p JOIN units u ON u.id=p.unit_id
             LEFT JOIN stock_movements sm ON sm.product_id=p.id AND sm.company_id=p.company_id AND sm.branch_id=:branch
             WHERE p.company_id=:company AND p.active=1 AND p.track_stock=1 {$filter}
             GROUP BY p.id,p.internal_code,p.description,p.minimum_stock,u.code
             ORDER BY (COALESCE(SUM(sm.quantity),0)<=p.minimum_stock) DESC,p.description LIMIT 300"
        );
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        return ['items' => $items, 'low' => count(array_filter($items, static fn(array $i): bool => (float) $i['quantity'] <= (float) $i['minimum_stock']))];
    }

    /** @return array<int,array<string,mixed>> */
    public function history(?int $productId = null): array
    {
        $user = Auth::user();
        $params = ['company' => $user['company_id'], 'branch' => $user['branch_id']];
        $filter = '';
        if ($productId) {
            $filter = ' AND sm.product_id=:product';
            $params['product'] = $productId;
        }
        $stmt = Database::connection()->prepare(
            "SELECT sm.*,p.description,l.name AS location_name,u.name AS user_name
             FROM stock_movements sm JOIN products p ON p.id=sm.product_id
             JOIN stock_locations l ON l.id=sm.location_id LEFT JOIN users u ON u.id=sm.user_id
             WHERE sm.company_id=:company AND sm.branch_id=:branch {$filter}
             ORDER BY sm.occurred_at DESC,sm.id DESC LIMIT 300"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function adjust(int $productId, int $locationId, string $quantity, string $reason): int
    {
        $user = Auth::user();
        $value = self::signedQuantity($quantity);
        $reason = trim($reason);
        if ($value === null || $value === '0.0000' || mb_strlen($reason) < 5) {
            throw new \InvalidArgumentException('Informe quantidade diferente de zero e justificativa com ao menos 5 caracteres.');
        }
        return Database::transactional(function (PDO $pdo) use ($user, $productId, $locationId, $value, $reason): int {
            $this->assertProductAndLocation($pdo, $productId, $locationId);
            $key = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare(
                'INSERT INTO stock_movements
                 (company_id,branch_id,location_id,product_id,movement_type,quantity,source_type,source_id,source_line,reason,user_id,occurred_at)
                 VALUES (:company,:branch,:location,:product,:type,:quantity,:source,:source_id,:line,:reason,:user,:at)'
            );
            $stmt->execute([
                'company' => $user['company_id'], 'branch' => $user['branch_id'], 'location' => $locationId,
                'product' => $productId, 'type' => 'adjustment', 'quantity' => $value, 'source' => 'stock_adjustment',
                'source_id' => $key, 'line' => '1', 'reason' => $reason, 'user' => $user['id'], 'at' => date('Y-m-d H:i:s'),
            ]);
            $id = (int) $pdo->lastInsertId();
            $this->postStockFinance($pdo, $user, $productId, (float) $value, 'stock_adjustment', (string) $id, 'Ajuste de estoque: '.$reason);
            Audit::log('stock.adjusted', 'stock_movements', $id, null, ['quantity' => $value, 'reason' => $reason]);
            return $id;
        });
    }

    public function createInventory(int $locationId, string $notes = ''): int
    {
        $user = Auth::user();
        return Database::transactional(function (PDO $pdo) use ($user, $locationId, $notes): int {
            $this->assertLocation($pdo, $locationId);
            $now = date('Y-m-d H:i:s');
            $pdo->prepare(
                'INSERT INTO inventory_counts (company_id,branch_id,location_id,status,notes,started_by,started_at)
                 VALUES (:company,:branch,:location,\'open\',:notes,:user,:at)'
            )->execute(['company' => $user['company_id'], 'branch' => $user['branch_id'], 'location' => $locationId,
                'notes' => trim($notes) ?: null, 'user' => $user['id'], 'at' => $now]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'INSERT INTO inventory_count_items (inventory_count_id,product_id,expected_quantity)
                 SELECT :inventory,p.id,COALESCE(SUM(sm.quantity),0) FROM products p
                 LEFT JOIN stock_movements sm ON sm.product_id=p.id AND sm.location_id=:location
                 WHERE p.company_id=:company AND p.active=1 AND p.track_stock=1 GROUP BY p.id'
            )->execute(['inventory' => $id, 'location' => $locationId, 'company' => $user['company_id']]);
            Audit::log('inventory.started', 'inventory_counts', $id);
            return $id;
        });
    }

    /** @param array<int|string,mixed> $counts */
    public function closeInventory(int $inventoryId, array $counts): void
    {
        $user = Auth::user();
        Database::transactional(function (PDO $pdo) use ($user, $inventoryId, $counts): void {
            $stmt = $pdo->prepare('SELECT * FROM inventory_counts WHERE id=:id AND company_id=:company AND branch_id=:branch AND status=\'open\'');
            $stmt->execute(['id' => $inventoryId, 'company' => $user['company_id'], 'branch' => $user['branch_id']]);
            $inventory = $stmt->fetch();
            if (!$inventory) {
                throw new \RuntimeException('Inventário aberto não encontrado.');
            }
            $items = $pdo->prepare('SELECT * FROM inventory_count_items WHERE inventory_count_id=:id');
            $items->execute(['id' => $inventoryId]);
            foreach ($items->fetchAll() as $item) {
                $counted = self::unsignedQuantity($counts[$item['product_id']] ?? '');
                if ($counted === null) {
                    throw new \InvalidArgumentException('Todas as contagens devem ser informadas.');
                }
                $difference = round((float) $counted - (float) $item['expected_quantity'], 4);
                $pdo->prepare('UPDATE inventory_count_items SET counted_quantity=:quantity WHERE id=:id')
                    ->execute(['quantity' => $counted, 'id' => $item['id']]);
                if ($difference != 0.0) {
                    $pdo->prepare(
                        'INSERT INTO stock_movements
                         (company_id,branch_id,location_id,product_id,movement_type,quantity,source_type,source_id,source_line,reason,user_id,occurred_at)
                         VALUES (:company,:branch,:location,:product,\'inventory\',:quantity,\'inventory_count\',:source,:line,:reason,:user,:at)'
                    )->execute(['company' => $user['company_id'], 'branch' => $user['branch_id'], 'location' => $inventory['location_id'],
                        'product' => $item['product_id'], 'quantity' => number_format($difference, 4, '.', ''),
                        'source' => (string) $inventoryId, 'line' => (string) $item['id'], 'reason' => 'Fechamento de inventário',
                        'user' => $user['id'], 'at' => date('Y-m-d H:i:s')]);
                    $this->postStockFinance($pdo, $user, (int) $item['product_id'], $difference, 'inventory_count', $inventoryId.'-'.$item['id'], 'Inventário #'.$inventoryId);
                }
            }
            $pdo->prepare('UPDATE inventory_counts SET status=\'closed\',closed_by=:user,closed_at=:at WHERE id=:id')
                ->execute(['user' => $user['id'], 'at' => date('Y-m-d H:i:s'), 'id' => $inventoryId]);
            Audit::log('inventory.closed', 'inventory_counts', $inventoryId);
        });
    }

    /** @param array<string,mixed> $user */
    private function postStockFinance(PDO $pdo, array $user, int $productId, float $qty, string $sourceType, string $sourceId, string $description): void
    {
        $cost = $pdo->prepare('SELECT cost_price FROM products WHERE id=:id AND company_id=:company');
        $cost->execute(['id' => $productId, 'company' => $user['company_id']]);
        $amount = round(abs($qty) * (float) $cost->fetchColumn(), 2);
        if ($amount <= 0) {
            return;
        }
        FinanceLedger::post($pdo, $user, [
            'direction' => $qty < 0 ? 'out' : 'in',
            'category' => FinanceLedger::STOCK,
            'description' => $description,
            'amount' => $amount,
            'status' => 'settled',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
    }

    private function assertProductAndLocation(PDO $pdo, int $product, int $location): void
    {
        $user = Auth::user();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM products p JOIN stock_locations l ON l.id=:location
             WHERE p.id=:product AND p.company_id=:company AND l.company_id=:company2 AND l.branch_id=:branch AND p.track_stock=1'
        );
        $stmt->execute(['location' => $location, 'product' => $product, 'company' => $user['company_id'],
            'company2' => $user['company_id'], 'branch' => $user['branch_id']]);
        if (!$stmt->fetchColumn()) {
            throw new \RuntimeException('Produto ou local de estoque inválido.');
        }
    }

    private function assertLocation(PDO $pdo, int $location): void
    {
        $user = Auth::user();
        $stmt = $pdo->prepare('SELECT 1 FROM stock_locations WHERE id=:id AND company_id=:company AND branch_id=:branch AND active=1');
        $stmt->execute(['id' => $location, 'company' => $user['company_id'], 'branch' => $user['branch_id']]);
        if (!$stmt->fetchColumn()) {
            throw new \RuntimeException('Local de estoque inválido.');
        }
    }

    private static function signedQuantity(mixed $value): ?string
    {
        $value = str_replace(',', '.', trim((string) $value));
        return preg_match('/^-?\d{1,11}(?:\.\d{1,4})?$/', $value) ? number_format((float) $value, 4, '.', '') : null;
    }

    private static function unsignedQuantity(mixed $value): ?string
    {
        $value = str_replace(',', '.', trim((string) $value));
        return preg_match('/^\d{1,11}(?:\.\d{1,4})?$/', $value) ? number_format((float) $value, 4, '.', '') : null;
    }
}
