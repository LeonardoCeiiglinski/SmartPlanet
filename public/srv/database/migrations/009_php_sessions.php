<?php

declare(strict_types=1);

return [
    'up' => static function (\PDO $pdo, string $driver): void {
        $data = $driver === 'mysql' ? 'MEDIUMTEXT NOT NULL' : 'TEXT NOT NULL';
        $options = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS php_sessions (
                id VARCHAR(128) PRIMARY KEY,
                data {$data},
                last_activity INTEGER NOT NULL
            ){$options}"
        );
    },
    'down' => static function (\PDO $pdo, string $driver): void {
        $pdo->exec('DROP TABLE IF EXISTS php_sessions');
    },
];
