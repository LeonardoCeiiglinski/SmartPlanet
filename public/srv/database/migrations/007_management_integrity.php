<?php

declare(strict_types=1);

return [
    'up' => static function (\PDO $pdo, string $driver): void {
        $pdo->exec('ALTER TABLE quotes ADD COLUMN idempotency_key VARCHAR(100)');
        $pdo->exec('ALTER TABLE sale_returns ADD COLUMN idempotency_key VARCHAR(100)');
        $pdo->exec('ALTER TABLE financial_settlements ADD COLUMN idempotency_key VARCHAR(100)');
        $pdo->exec('ALTER TABLE financial_settlements ADD COLUMN reversed_settlement_id INTEGER');
        $pdo->exec('CREATE UNIQUE INDEX ux_quote_idempotency ON quotes (company_id, idempotency_key)');
        $pdo->exec('CREATE UNIQUE INDEX ux_return_idempotency ON sale_returns (company_id, idempotency_key)');
        $pdo->exec('CREATE UNIQUE INDEX ux_settlement_idempotency ON financial_settlements (financial_entry_id, idempotency_key)');
        $pdo->exec('CREATE INDEX idx_returns_sale ON sale_returns (company_id, branch_id, sale_id, status)');
        $pdo->exec('CREATE INDEX idx_settlements_date ON financial_settlements (settled_at)');
    },
    'down' => static function (\PDO $pdo, string $driver): void {
        foreach (['idx_settlements_date', 'idx_returns_sale', 'ux_settlement_idempotency', 'ux_return_idempotency', 'ux_quote_idempotency'] as $index) {
            if ($driver === 'sqlite') {
                $pdo->exec("DROP INDEX IF EXISTS {$index}");
            }
        }
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE financial_settlements DROP COLUMN reversed_settlement_id, DROP COLUMN idempotency_key');
            $pdo->exec('ALTER TABLE sale_returns DROP COLUMN idempotency_key');
            $pdo->exec('ALTER TABLE quotes DROP COLUMN idempotency_key');
        }
    },
];
