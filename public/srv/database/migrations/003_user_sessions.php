<?php

declare(strict_types=1);

return [
    'up' => static function (\PDO $pdo, string $driver): void {
        $id = $driver === 'mysql'
            ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $big = $driver === 'mysql' ? 'BIGINT UNSIGNED' : 'INTEGER';
        $options = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $pdo->exec(
            "CREATE TABLE user_sessions (
                id {$id}, user_id {$big} NOT NULL, session_hash VARCHAR(128) NOT NULL UNIQUE,
                ip_address VARCHAR(64), user_agent VARCHAR(500), last_activity_at DATETIME NOT NULL,
                logged_out_at DATETIME, created_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ){$options}"
        );
    },
    'down' => static function (\PDO $pdo, string $driver): void {
        $pdo->exec('DROP TABLE IF EXISTS user_sessions');
    },
];

