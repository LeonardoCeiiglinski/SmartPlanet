<?php

declare(strict_types=1);

use App\Domain\Finance\FinanceLedger;

return [
    'up' => static function (PDO $pdo, string $driver): void {
        try {
            if ($driver === 'mysql') {
                $pdo->exec('ALTER TABLE financial_categories ADD COLUMN code VARCHAR(40) NULL');
            } else {
                $pdo->exec('ALTER TABLE financial_categories ADD COLUMN code VARCHAR(40)');
            }
        } catch (Throwable) {
        }
        try {
            $pdo->exec('CREATE UNIQUE INDEX ux_financial_category_code ON financial_categories (company_id, code)');
        } catch (Throwable) {
        }
        $companies = $pdo->query('SELECT id FROM companies')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($companies as $companyId) {
            FinanceLedger::seedCompany($pdo, (int) $companyId);
        }
    },
    'down' => static function (PDO $pdo, string $driver): void {
        try {
            $pdo->exec('DROP INDEX ux_financial_category_code');
        } catch (Throwable) {
        }
    },
];
