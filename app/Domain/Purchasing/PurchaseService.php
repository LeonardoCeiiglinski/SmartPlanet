<?php

declare(strict_types=1);

namespace App\Domain\Purchasing;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Domain\Finance\FinanceLedger;
use PDO;

final class PurchaseService
{
    /** @return array<int,array<string,mixed>> */
    public function orders(): array
    {
        $user = Auth::user();
        $stmt = Database::connection()->prepare(
            'SELECT po.*,s.name AS supplier_name FROM purchase_orders po JOIN suppliers s ON s.id=po.supplier_id
             WHERE po.company_id=:company AND po.branch_id=:branch ORDER BY po.created_at DESC LIMIT 200'
        );
        $stmt->execute(['company' => $user['company_id'], 'branch' => $user['branch_id']]);
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $user = Auth::user();
        $stmt = Database::connection()->prepare(
            'SELECT po.*,s.name AS supplier_name FROM purchase_orders po JOIN suppliers s ON s.id=po.supplier_id
             WHERE po.id=:id AND po.company_id=:company AND po.branch_id=:branch'
        );
        $stmt->execute(['id' => $id, 'company' => $user['company_id'], 'branch' => $user['branch_id']]);
        $order = $stmt->fetch();
        if (!$order) {
            return null;
        }
        $items = Database::connection()->prepare(
            'SELECT poi.*,p.description,p.internal_code FROM purchase_order_items poi JOIN products p ON p.id=poi.product_id
             WHERE poi.purchase_order_id=:order ORDER BY poi.id'
        );
        $items->execute(['order' => $id]);
        $order['items'] = $items->fetchAll();
        return $order;
    }

    /** @param array<int,array<string,mixed>> $items */
    public function create(int $supplierId, array $items, string $expectedAt = '', string $notes = ''): int
    {
        $user = Auth::user();
        if (!$items) {
            throw new \InvalidArgumentException('Inclua ao menos um item.');
        }
        return Database::transactional(function (PDO $pdo) use ($user, $supplierId, $items, $expectedAt, $notes): int {
            $supplier = $pdo->prepare('SELECT 1 FROM suppliers WHERE id=:id AND company_id=:company AND active=1');
            $supplier->execute(['id' => $supplierId, 'company' => $user['company_id']]);
            if (!$supplier->fetchColumn()) {
                throw new \InvalidArgumentException('Fornecedor inválido.');
            }
            $normalized = [];
            $total = 0.0;
            foreach ($items as $item) {
                $product = (int) ($item['product_id'] ?? 0);
                $quantity = self::quantity($item['quantity'] ?? '');
                $cost = self::money($item['unit_cost'] ?? '');
                if ($product < 1 || $quantity === null || (float) $quantity <= 0 || $cost === null) {
                    throw new \InvalidArgumentException('Item, quantidade ou custo inválido.');
                }
                $check = $pdo->prepare('SELECT 1 FROM products WHERE id=:id AND company_id=:company AND active=1 AND track_stock=1');
                $check->execute(['id' => $product, 'company' => $user['company_id']]);
                if (!$check->fetchColumn()) {
                    throw new \InvalidArgumentException('Produto inválido no pedido.');
                }
                $subtotal = round((float) $quantity * (float) $cost, 2);
                $total += $subtotal;
                $normalized[] = [$product, $quantity, $cost, number_format($subtotal, 2, '.', '')];
            }
            $now = date('Y-m-d H:i:s');
            $number = 'PC-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $pdo->prepare(
                'INSERT INTO purchase_orders
                 (company_id,branch_id,supplier_id,order_number,status,expected_at,subtotal,total,notes,created_by,created_at,updated_at)
                 VALUES (:company,:branch,:supplier,:number,\'ordered\',:expected,:subtotal,:total,:notes,:user,:created,:updated)'
            )->execute(['company' => $user['company_id'], 'branch' => $user['branch_id'], 'supplier' => $supplierId,
                'number' => $number, 'expected' => trim($expectedAt) ?: null, 'subtotal' => number_format($total, 2, '.', ''),
                'total' => number_format($total, 2, '.', ''), 'notes' => trim($notes) ?: null,
                'user' => $user['id'], 'created' => $now, 'updated' => $now]);
            $id = (int) $pdo->lastInsertId();
            $insert = $pdo->prepare(
                'INSERT INTO purchase_order_items (purchase_order_id,product_id,quantity,unit_cost,subtotal)
                 VALUES (:order,:product,:quantity,:cost,:subtotal)'
            );
            foreach ($normalized as [$product, $quantity, $cost, $subtotal]) {
                $insert->execute(['order' => $id, 'product' => $product, 'quantity' => $quantity, 'cost' => $cost, 'subtotal' => $subtotal]);
            }
            Audit::log('purchase.created', 'purchase_orders', $id);
            return $id;
        });
    }

    /** @param array<int|string,mixed> $quantities */
    public function receive(int $orderId, int $locationId, string $key, array $quantities, string $notes = ''): int
    {
        $user = Auth::user();
        $key = trim($key);
        if ($key === '' || strlen($key) > 100) {
            throw new \InvalidArgumentException('Chave idempotente inválida.');
        }
        return Database::transactional(function (PDO $pdo) use ($user, $orderId, $locationId, $key, $quantities, $notes): int {
            $existing = $pdo->prepare('SELECT id FROM purchase_receipts WHERE purchase_order_id=:order AND idempotency_key=:key');
            $existing->execute(['order' => $orderId, 'key' => $key]);
            if ($id = $existing->fetchColumn()) {
                return (int) $id;
            }
            $order = $this->find($orderId);
            if (!$order || in_array($order['status'], ['cancelled', 'received'], true)) {
                throw new \RuntimeException('Pedido não pode ser recebido.');
            }
            $location = $pdo->prepare('SELECT 1 FROM stock_locations WHERE id=:id AND company_id=:company AND branch_id=:branch AND active=1');
            $location->execute(['id' => $locationId, 'company' => $user['company_id'], 'branch' => $user['branch_id']]);
            if (!$location->fetchColumn()) {
                throw new \InvalidArgumentException('Local de estoque inválido.');
            }
            $received = [];
            foreach ($order['items'] as $item) {
                $quantity = self::quantity($quantities[$item['id']] ?? '0');
                $remaining = round((float) $item['quantity'] - (float) $item['received_quantity'], 4);
                if ($quantity === null || (float) $quantity < 0 || (float) $quantity > $remaining) {
                    throw new \InvalidArgumentException('Quantidade recebida excede o saldo do item.');
                }
                if ((float) $quantity > 0) {
                    $received[] = [$item, $quantity];
                }
            }
            if (!$received) {
                throw new \InvalidArgumentException('Informe alguma quantidade para recebimento.');
            }
            $now = date('Y-m-d H:i:s');
            $number = 'REC-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $pdo->prepare(
                'INSERT INTO purchase_receipts (purchase_order_id,receipt_number,location_id,received_by,received_at,notes,idempotency_key)
                 VALUES (:order,:number,:location,:user,:at,:notes,:key)'
            )->execute(['order' => $orderId, 'number' => $number, 'location' => $locationId, 'user' => $user['id'],
                'at' => $now, 'notes' => trim($notes) ?: null, 'key' => $key]);
            $receiptId = (int) $pdo->lastInsertId();
            foreach ($received as [$item, $quantity]) {
                $pdo->prepare(
                    'INSERT INTO purchase_receipt_items (purchase_receipt_id,purchase_order_item_id,quantity,unit_cost,created_at)
                     VALUES (:receipt,:item,:quantity,:cost,:at)'
                )->execute(['receipt' => $receiptId, 'item' => $item['id'], 'quantity' => $quantity, 'cost' => $item['unit_cost'], 'at' => $now]);
                $pdo->prepare('UPDATE purchase_order_items SET received_quantity=received_quantity+:quantity WHERE id=:id')
                    ->execute(['quantity' => $quantity, 'id' => $item['id']]);
                $pdo->prepare(
                    'INSERT INTO stock_movements
                     (company_id,branch_id,location_id,product_id,movement_type,quantity,unit_cost,source_type,source_id,source_line,reason,user_id,occurred_at)
                     VALUES (:company,:branch,:location,:product,\'purchase_receipt\',:quantity,:cost,\'purchase_receipt\',:source,:line,:reason,:user,:at)'
                )->execute(['company' => $user['company_id'], 'branch' => $user['branch_id'], 'location' => $locationId,
                    'product' => $item['product_id'], 'quantity' => $quantity, 'cost' => $item['unit_cost'],
                    'source' => (string) $receiptId, 'line' => (string) $item['id'], 'reason' => 'Recebimento ' . $number,
                    'user' => $user['id'], 'at' => $now]);
                $pdo->prepare('UPDATE products SET cost_price=:cost,version=version+1,updated_at=:at WHERE id=:id AND company_id=:company')
                    ->execute(['cost' => $item['unit_cost'], 'at' => $now, 'id' => $item['product_id'], 'company' => $user['company_id']]);
            }
            $remaining = $pdo->prepare('SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id=:order AND received_quantity<quantity');
            $remaining->execute(['order' => $orderId]);
            $status = (int) $remaining->fetchColumn() === 0 ? 'received' : 'partially_received';
            $pdo->prepare('UPDATE purchase_orders SET status=:status,updated_at=:at WHERE id=:id AND company_id=:company AND branch_id=:branch')
                ->execute(['status' => $status, 'at' => $now, 'id' => $orderId, 'company' => $user['company_id'], 'branch' => $user['branch_id']]);
            $payable = 0.0;
            foreach ($received as [$item, $quantity]) {
                $payable += round((float) $quantity * (float) $item['unit_cost'], 2);
            }
            if ($payable > 0) {
                FinanceLedger::post($pdo, $user, [
                    'direction' => 'out',
                    'category' => FinanceLedger::PURCHASES,
                    'description' => 'Compra '.$order['order_number'].' · '.$number,
                    'document' => $number,
                    'amount' => $payable,
                    'status' => 'open',
                    'source_type' => 'purchase_receipt',
                    'source_id' => (string) $receiptId,
                    'supplier_id' => $order['supplier_id'],
                ]);
            }
            Audit::log('purchase.received', 'purchase_receipts', $receiptId, null, ['order_id' => $orderId, 'status' => $status]);
            return $receiptId;
        });
    }

    public function cancel(int $orderId, string $reason): void
    {
        $user = Auth::user();
        if (mb_strlen(trim($reason)) < 5) {
            throw new \InvalidArgumentException('Justificativa obrigatória.');
        }
        Database::transactional(function (PDO $pdo) use ($user, $orderId, $reason): void {
            $order = $this->find($orderId);
            if (!$order || !in_array($order['status'], ['ordered', 'draft'], true)) {
                throw new \RuntimeException('Só é possível cancelar pedido ainda não recebido.');
            }
            $receipts = $pdo->prepare('SELECT COUNT(*) FROM purchase_receipts WHERE purchase_order_id=:id');
            $receipts->execute(['id' => $orderId]);
            if ((int) $receipts->fetchColumn() > 0) {
                throw new \RuntimeException('Pedido com recebimento não pode ser cancelado. Estorne o financeiro da compra se necessário.');
            }
            $pdo->prepare('UPDATE purchase_orders SET status=\'cancelled\',notes=:notes,updated_at=:at WHERE id=:id AND company_id=:company')
                ->execute([
                    'notes' => trim(($order['notes'] ? $order['notes'] . ' | ' : '') . 'Cancelado: ' . trim($reason)),
                    'at' => date('Y-m-d H:i:s'),
                    'id' => $orderId,
                    'company' => $user['company_id'],
                ]);
            Audit::log('purchase.cancelled', 'purchase_orders', $orderId, ['status' => $order['status']], ['reason' => $reason]);
        });
    }

    private static function quantity(mixed $value): ?string
    {
        $value = str_replace(',', '.', trim((string) $value));
        return preg_match('/^\d{1,11}(?:\.\d{1,4})?$/', $value) ? number_format((float) $value, 4, '.', '') : null;
    }

    private static function money(mixed $value): ?string
    {
        $value = str_replace(',', '.', trim((string) $value));
        return preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $value) ? number_format((float) $value, 2, '.', '') : null;
    }
}
