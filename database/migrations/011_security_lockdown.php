<?php

declare(strict_types=1);

use App\Setup\MasterAccount;

return [
    'up' => static function (PDO $pdo, string $driver): void {
        MasterAccount::apply($pdo, $driver);
    },
    'down' => static function (PDO $pdo, string $driver): void {
        if ($driver !== 'pgsql') {
            return;
        }
        $pdo->exec('DROP FUNCTION IF EXISTS app_private.lock_api_access()');
        $pdo->exec('DROP FUNCTION IF EXISTS app_private.password_is_strong(text)');
        $pdo->exec('DROP SCHEMA IF EXISTS app_private');
    },
];
