<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Core\Audit;
use PDO;

final class FinanceLedger
{
    public const SALES = 'vendas';
    public const COGS = 'cmv';
    public const RETURNS = 'devolucoes';
    public const PURCHASES = 'compras';
    public const CASH_IN = 'caixa_reforco';
    public const CASH_OUT = 'caixa_sangria';
    public const STOCK = 'ajuste_estoque';
    public const MANUAL = 'manual';

    /** @param array<string,mixed> $user */
    public static function post(PDO $pdo, array $user, array $data): int
    {
        $sourceType = (string) ($data['source_type'] ?? '');
        $sourceId = (string) ($data['source_id'] ?? '');
        $installment = max(1, (int) ($data['installment'] ?? 1));
        if ($sourceType === '' || $sourceId === '') {
            throw new \InvalidArgumentException('Origem financeira obrigatória.');
        }
        $existing = $pdo->prepare(
            'SELECT id FROM financial_entries
             WHERE company_id=:company AND source_type=:type AND source_id=:source AND installment_number=:installment'
        );
        $existing->execute([
            'company' => $user['company_id'],
            'type' => $sourceType,
            'source' => $sourceId,
            'installment' => $installment,
        ]);
        if ($id = $existing->fetchColumn()) {
            return (int) $id;
        }

        $amount = self::money((float) $data['amount']);
        if ((float) $amount <= 0) {
            throw new \InvalidArgumentException('Valor financeiro inválido.');
        }
        $direction = ($data['direction'] ?? '') === 'out' ? 'out' : 'in';
        $status = in_array($data['status'] ?? 'settled', ['open', 'partial', 'settled', 'cancelled'], true)
            ? (string) $data['status']
            : 'settled';
        $settled = $status === 'settled' ? $amount : self::money((float) ($data['settled_amount'] ?? 0));
        $now = date('Y-m-d H:i:s');
        $date = (string) ($data['date'] ?? date('Y-m-d'));
        $pdo->prepare(
            'INSERT INTO financial_entries
             (company_id,branch_id,category_id,customer_id,supplier_id,direction,description,document_number,
              issue_date,due_date,amount,settled_amount,status,source_type,source_id,installment_number,installment_count,
              created_by,created_at,updated_at)
             VALUES
             (:company,:branch,:category,:customer,:supplier,:direction,:description,:document,
              :issue,:due,:amount,:settled,:status,:source_type,:source_id,:installment,:count,
              :user,:at,:at)'
        )->execute([
            'company' => $user['company_id'],
            'branch' => $user['branch_id'],
            'category' => self::categoryId($pdo, (int) $user['company_id'], (string) ($data['category'] ?? self::MANUAL), $direction),
            'customer' => $data['customer_id'] ?? null,
            'supplier' => $data['supplier_id'] ?? null,
            'direction' => $direction,
            'description' => mb_substr(trim((string) ($data['description'] ?? 'Lançamento')), 0, 255),
            'document' => $data['document'] ?? null,
            'issue' => $date,
            'due' => (string) ($data['due'] ?? $date),
            'amount' => $amount,
            'settled' => $settled,
            'status' => $status,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'installment' => $installment,
            'count' => max(1, (int) ($data['installment_count'] ?? 1)),
            'user' => $user['id'],
            'at' => $now,
        ]);
        $id = (int) $pdo->lastInsertId();
        Audit::log('finance.posted', 'financial_entries', $id, null, [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'amount' => $amount,
            'direction' => $direction,
        ]);
        return $id;
    }

    public static function cancelBySource(PDO $pdo, int $companyId, string $sourceType, string $sourceId): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'UPDATE financial_entries SET status=\'cancelled\', updated_at=:at
             WHERE company_id=:company AND source_type=:type AND source_id=:source AND status<>\'cancelled\''
        );
        $stmt->execute(['at' => $now, 'company' => $companyId, 'type' => $sourceType, 'source' => $sourceId]);
        return $stmt->rowCount();
    }

    public static function cancelBySourcePrefix(PDO $pdo, int $companyId, string $sourceType, string $prefix): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'UPDATE financial_entries SET status=\'cancelled\', updated_at=:at
             WHERE company_id=:company AND source_type=:type AND source_id LIKE :source AND status<>\'cancelled\''
        );
        $stmt->execute(['at' => $now, 'company' => $companyId, 'type' => $sourceType, 'source' => $prefix . '%']);
        return $stmt->rowCount();
    }

    public static function seedCompany(PDO $pdo, int $companyId): void
    {
        foreach (self::catalog() as $code => [$name, $direction]) {
            self::categoryId($pdo, $companyId, $code, $direction, $name);
        }
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function catalog(): array
    {
        return [
            self::SALES => ['Vendas', 'in'],
            self::COGS => ['Custo das mercadorias vendidas', 'out'],
            self::RETURNS => ['Devoluções de venda', 'out'],
            self::PURCHASES => ['Compras a pagar', 'out'],
            self::CASH_IN => ['Reforço de caixa', 'in'],
            self::CASH_OUT => ['Sangria de caixa', 'out'],
            self::STOCK => ['Ajuste de estoque', 'out'],
            self::MANUAL => ['Lançamento manual', 'in'],
        ];
    }

    private static function categoryId(PDO $pdo, int $companyId, string $code, string $direction, ?string $name = null): int
    {
        $code = preg_replace('/[^a-z0-9_]/', '', strtolower($code)) ?: self::MANUAL;
        $hasCode = self::hasCodeColumn($pdo);
        if ($hasCode) {
            $find = $pdo->prepare('SELECT id FROM financial_categories WHERE company_id=:company AND code=:code');
            $find->execute(['company' => $companyId, 'code' => $code]);
            if ($id = $find->fetchColumn()) {
                return (int) $id;
            }
        }
        $label = $name ?? (self::catalog()[$code][0] ?? $code);
        $dir = self::catalog()[$code][1] ?? $direction;
        if ($hasCode) {
            $pdo->prepare(
                'INSERT INTO financial_categories (company_id,name,direction,active,code) VALUES (:company,:name,:direction,1,:code)'
            )->execute(['company' => $companyId, 'name' => $label, 'direction' => $dir, 'code' => $code]);
        } else {
            $find = $pdo->prepare('SELECT id FROM financial_categories WHERE company_id=:company AND name=:name AND direction=:direction');
            $find->execute(['company' => $companyId, 'name' => $label, 'direction' => $dir]);
            if ($id = $find->fetchColumn()) {
                return (int) $id;
            }
            $pdo->prepare(
                'INSERT INTO financial_categories (company_id,name,direction,active) VALUES (:company,:name,:direction,1)'
            )->execute(['company' => $companyId, 'name' => $label, 'direction' => $dir]);
        }
        return (int) $pdo->lastInsertId();
    }

    private static function hasCodeColumn(PDO $pdo): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $cols = $pdo->query('PRAGMA table_info(financial_categories)')->fetchAll();
            $cache = (bool) array_filter($cols, static fn (array $c): bool => ($c['name'] ?? '') === 'code');
            return $cache;
        }
        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare(
                "SELECT 1 FROM information_schema.columns
                 WHERE table_schema = current_schema()
                   AND table_name = 'financial_categories'
                   AND column_name = 'code'"
            );
            $stmt->execute();
            $cache = (bool) $stmt->fetchColumn();
            return $cache;
        }
        $stmt = $pdo->query("SHOW COLUMNS FROM financial_categories LIKE 'code'");
        $cache = $stmt !== false && (bool) $stmt->fetch();
        return $cache;
    }

    private static function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
