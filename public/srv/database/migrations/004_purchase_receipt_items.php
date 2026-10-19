<?php

declare(strict_types=1);

return [
    'up' => static function (\PDO $pdo, string $driver): void {
        $big = $driver === 'mysql' ? 'BIGINT UNSIGNED' : 'INTEGER';
        $id = $driver === 'mysql'
            ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $options = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

        $pdo->exec('ALTER TABLE purchase_receipts ADD COLUMN idempotency_key VARCHAR(100)');
        $pdo->exec('CREATE UNIQUE INDEX ux_purchase_receipt_idempotency ON purchase_receipts (purchase_order_id, idempotency_key)');
        $pdo->exec("CREATE TABLE purchase_receipt_items (
            id {$id},
            purchase_receipt_id {$big} NOT NULL,
            purchase_order_item_id {$big} NOT NULL,
            quantity DECIMAL(15,4) NOT NULL,
            unit_cost DECIMAL(15,2) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE (purchase_receipt_id, purchase_order_item_id),
            FOREIGN KEY (purchase_receipt_id) REFERENCES purchase_receipts(id) ON DELETE CASCADE,
            FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items(id),
            CHECK (quantity > 0)
        ){$options}");
    },
    'down' => static function (\PDO $pdo, string $driver): void {
        $pdo->exec('DROP TABLE IF EXISTS purchase_receipt_items');
        $pdo->exec($driver === 'mysql'
            ? 'DROP INDEX ux_purchase_receipt_idempotency ON purchase_receipts'
            : 'DROP INDEX ux_purchase_receipt_idempotency');
        // SQLite não suporta remover coluna; ela é inofensiva após rollback.
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE purchase_receipts DROP COLUMN idempotency_key');
        }
    },
];
