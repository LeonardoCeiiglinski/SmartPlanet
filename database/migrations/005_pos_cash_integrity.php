<?php

declare(strict_types=1);

return [
    'up' => static function (\PDO $pdo, string $driver): void {
        $pdo->exec('ALTER TABLE cash_sessions ADD COLUMN open_guard VARCHAR(40)');
        $pdo->exec('ALTER TABLE cash_sessions ADD COLUMN closing_reason VARCHAR(255)');
        $pdo->exec('CREATE UNIQUE INDEX ux_cash_session_open ON cash_sessions (company_id, branch_id, cash_register_id, open_guard)');
        $pdo->exec('CREATE UNIQUE INDEX ux_cash_movement_source ON cash_movements (cash_session_id, movement_type, source_type, source_id)');
        $pdo->exec('CREATE UNIQUE INDEX ux_sale_payment_once ON sale_payments (sale_id, payment_method_id, reference)');
        $pdo->exec('CREATE INDEX idx_sale_lookup ON sales (company_id, branch_id, status, created_at)');
    },
    'down' => static function (\PDO $pdo, string $driver): void {
        foreach (['idx_sale_lookup', 'ux_sale_payment_once', 'ux_cash_movement_source', 'ux_cash_session_open'] as $index) {
            $pdo->exec($driver === 'mysql' ? "DROP INDEX {$index} ON " . ($index === 'idx_sale_lookup' ? 'sales' : ($index === 'ux_sale_payment_once' ? 'sale_payments' : ($index === 'ux_cash_movement_source' ? 'cash_movements' : 'cash_sessions'))) : "DROP INDEX IF EXISTS {$index}");
        }
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE cash_sessions DROP COLUMN open_guard, DROP COLUMN closing_reason');
        }
    },
];
