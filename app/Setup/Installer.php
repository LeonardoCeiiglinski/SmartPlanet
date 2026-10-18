<?php

declare(strict_types=1);

namespace App\Setup;

use App\Core\Database;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class Installer
{
    public static function isInstalled(): bool
    {
        try {
            return (int) Database::connection()->query('SELECT COUNT(*) FROM companies')->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function run(string $company, string $email, string $password, string $name = 'Administrador', ?string $document = null): void
    {
        $company = trim($company);
        $email = trim($email);
        $name = trim($name) ?: 'Administrador';
        $document = preg_replace('/\D+/', '', (string) $document) ?: null;
        if ($company === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
            throw new InvalidArgumentException('Informe empresa, e-mail válido e senha com pelo menos 10 caracteres.');
        }
        if (self::isInstalled()) {
            throw new RuntimeException('A instalação já possui uma empresa.');
        }

        Database::transactional(static function (PDO $pdo) use ($company, $email, $password, $name, $document): void {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare(
                'INSERT INTO companies (legal_name, trade_name, document, timezone, active, created_at, updated_at)
                 VALUES (:legal, :trade, :document, :timezone, 1, :created, :updated)'
            )->execute([
                'legal' => $company,
                'trade' => $company,
                'document' => $document,
                'timezone' => 'America/Sao_Paulo',
                'created' => $now,
                'updated' => $now,
            ]);
            $companyId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO branches (company_id, name, code, document, active, created_at, updated_at)
                 VALUES (:company, :name, :code, :document, 1, :created, :updated)'
            )->execute([
                'company' => $companyId,
                'name' => 'Matriz',
                'code' => 'MATRIZ',
                'document' => $document,
                'created' => $now,
                'updated' => $now,
            ]);
            $branchId = (int) $pdo->lastInsertId();

            $roles = [
                'Administrador' => 'Acesso integral ao sistema',
                'Gerente' => 'Gestão comercial e autorizações',
                'Operador de caixa' => 'Operação de PDV e caixa',
            ];
            $roleIds = [];
            $roleStmt = $pdo->prepare(
                'INSERT INTO roles (company_id, name, description, created_at)
                 VALUES (:company, :name, :description, :created)'
            );
            foreach ($roles as $roleName => $description) {
                $roleStmt->execute([
                    'company' => $companyId,
                    'name' => $roleName,
                    'description' => $description,
                    'created' => $now,
                ]);
                $roleIds[$roleName] = (int) $pdo->lastInsertId();
            }

            $permissionIds = $pdo->query('SELECT id, code FROM permissions')->fetchAll(PDO::FETCH_KEY_PAIR);
            $grant = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:role, :permission)');
            $operatorCodes = ['dashboard.view', 'products.view', 'labels.print', 'stock.view', 'pos.sell', 'sales.view', 'cash.open', 'cash.move', 'cash.close', 'procedures.view'];
            $managerDenied = ['users.manage', 'settings.manage'];
            foreach ($permissionIds as $permissionId => $code) {
                $grant->execute(['role' => $roleIds['Administrador'], 'permission' => (int) $permissionId]);
                if (!in_array($code, $managerDenied, true)) {
                    $grant->execute(['role' => $roleIds['Gerente'], 'permission' => (int) $permissionId]);
                }
                if (in_array($code, $operatorCodes, true)) {
                    $grant->execute(['role' => $roleIds['Operador de caixa'], 'permission' => (int) $permissionId]);
                }
            }

            $pdo->prepare(
                'INSERT INTO users (company_id, branch_id, role_id, name, email, password_hash, active, is_master, created_at, updated_at)
                 VALUES (:company, :branch, :role, :name, :email, :password, 1, 1, :created, :updated)'
            )->execute([
                'company' => $companyId,
                'branch' => $branchId,
                'role' => $roleIds['Administrador'],
                'name' => $name,
                'email' => mb_strtolower($email),
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'created' => $now,
                'updated' => $now,
            ]);

            $unitStmt = $pdo->prepare(
                'INSERT INTO units (company_id, code, name, decimal_places, active)
                 VALUES (:company, :code, :name, :decimals, 1)'
            );
            foreach ([['UN', 'Unidade', 0], ['KG', 'Quilograma', 3], ['LT', 'Litro', 3], ['SV', 'Serviço', 0]] as [$code, $unitName, $decimals]) {
                $unitStmt->execute(['company' => $companyId, 'code' => $code, 'name' => $unitName, 'decimals' => $decimals]);
            }

            $pdo->prepare(
                'INSERT INTO stock_locations (company_id, branch_id, code, name, active)
                 VALUES (:company, :branch, :code, :name, 1)'
            )->execute(['company' => $companyId, 'branch' => $branchId, 'code' => 'PRINCIPAL', 'name' => 'Estoque principal']);
            $pdo->prepare(
                'INSERT INTO cash_registers (company_id, branch_id, name, code, active)
                 VALUES (:company, :branch, :name, :code, 1)'
            )->execute(['company' => $companyId, 'branch' => $branchId, 'name' => 'Caixa principal', 'code' => 'CX01']);

            $paymentStmt = $pdo->prepare(
                'INSERT INTO payment_methods
                 (company_id, code, name, method_type, requires_confirmation, max_installments, active)
                 VALUES (:company, :code, :name, :type, :confirmation, :installments, 1)'
            );
            foreach ([
                ['DINHEIRO', 'Dinheiro', 'cash', 0, 1],
                ['PIX', 'PIX', 'pix', 1, 1],
                ['DEBITO', 'Cartão de débito', 'debit', 1, 1],
                ['CREDITO', 'Cartão de crédito', 'credit', 1, 12],
            ] as [$code, $methodName, $type, $confirmation, $installments]) {
                $paymentStmt->execute([
                    'company' => $companyId, 'code' => $code, 'name' => $methodName,
                    'type' => $type, 'confirmation' => $confirmation, 'installments' => $installments,
                ]);
            }

            $pdo->prepare(
                'INSERT INTO financial_accounts (company_id, branch_id, name, account_type, active)
                 VALUES (:company, :branch, :name, :type, 1)'
            )->execute(['company' => $companyId, 'branch' => $branchId, 'name' => 'Caixa', 'type' => 'cash']);
        });
    }
}
