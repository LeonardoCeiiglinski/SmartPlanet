<?php

declare(strict_types=1);

return [
    'up' => static function (\PDO $pdo, string $driver): void {
        $id = $driver === 'mysql'
            ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $big = $driver === 'mysql' ? 'BIGINT UNSIGNED' : 'INTEGER';
        $json = $driver === 'mysql' ? 'JSON' : 'TEXT';
        $options = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $pdo->exec("CREATE TABLE suspended_sales (
            id {$id}, company_id {$big} NOT NULL, branch_id {$big} NOT NULL, operator_id {$big} NOT NULL,
            customer_id {$big}, idempotency_key VARCHAR(100) NOT NULL, label VARCHAR(100),
            payload_json {$json} NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, completed_sale_id {$big},
            UNIQUE (company_id, idempotency_key),
            FOREIGN KEY (company_id) REFERENCES companies(id), FOREIGN KEY (branch_id) REFERENCES branches(id),
            FOREIGN KEY (operator_id) REFERENCES users(id), FOREIGN KEY (customer_id) REFERENCES customers(id),
            FOREIGN KEY (completed_sale_id) REFERENCES sales(id)
        ){$options}");
        $pdo->exec('CREATE INDEX idx_suspended_sales_scope ON suspended_sales (company_id, branch_id, operator_id, status, updated_at)');
    },
    'down' => static function (\PDO $pdo, string $driver): void {
        $pdo->exec('DROP TABLE IF EXISTS suspended_sales');
    },
];
