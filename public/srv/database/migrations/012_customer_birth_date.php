<?php

declare(strict_types=1);

return [
    'up' => static function (PDO $pdo, string $driver): void {
        try {
            if ($driver === 'mysql') {
                $pdo->exec('ALTER TABLE customers ADD COLUMN birth_date DATE NULL');
            } else {
                $pdo->exec('ALTER TABLE customers ADD COLUMN birth_date DATE');
            }
        } catch (Throwable) {
        }
    },
    'down' => static function (PDO $pdo, string $driver): void {
    },
];
