<?php

declare(strict_types=1);

namespace App\Setup;

use PDO;
use RuntimeException;

final class MasterAccount
{
    public const EMAIL = 'leonardoceiglinskid@gmail.com';
    public const NAME = 'Leonardo Ceiglinski';
    private const HASH = '$2y$12$E3Y6g3xZyC71sRMarWPkuOcfTrirfjbFnk7xPXBHy0ChxudGxV2z6';

    public static function apply(PDO $pdo, string $driver): void
    {
        if ($driver === 'pgsql') {
            self::execScript($pdo, dirname(__DIR__, 2) . '/database/security/lockdown.sql');
            self::execScript($pdo, dirname(__DIR__, 2) . '/database/security/seed_master.sql');
            return;
        }
        if ((int) $pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn() > 0) {
            self::attach($pdo);
        }
    }

    public static function attach(PDO $pdo): void
    {
        $now = date('Y-m-d H:i:s');
        $existing = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
        $existing->execute(['email' => self::EMAIL]);
        $userId = $existing->fetchColumn();
        if ($userId !== false) {
            $pdo->prepare(
                'UPDATE users
                 SET password_hash = :password, is_master = 1, active = 1, name = :name, updated_at = :updated
                 WHERE id = :id'
            )->execute([
                'password' => self::HASH,
                'name' => self::NAME,
                'updated' => $now,
                'id' => (int) $userId,
            ]);
            return;
        }

        $companyId = (int) $pdo->query('SELECT id FROM companies ORDER BY id LIMIT 1')->fetchColumn();
        $branch = $pdo->prepare('SELECT id FROM branches WHERE company_id = :company ORDER BY id LIMIT 1');
        $branch->execute(['company' => $companyId]);
        $branchId = (int) $branch->fetchColumn();
        $role = $pdo->prepare("SELECT id FROM roles WHERE company_id = :company AND name = 'Administrador' ORDER BY id LIMIT 1");
        $role->execute(['company' => $companyId]);
        $roleId = (int) $role->fetchColumn();
        if ($companyId === 0 || $branchId === 0 || $roleId === 0) {
            throw new RuntimeException('Estrutura da empresa incompleta para cadastrar o administrador master.');
        }
        $pdo->prepare(
            'INSERT INTO users (company_id, branch_id, role_id, name, email, password_hash, active, is_master, created_at, updated_at)
             VALUES (:company, :branch, :role, :name, :email, :password, 1, 1, :created, :updated)'
        )->execute([
            'company' => $companyId,
            'branch' => $branchId,
            'role' => $roleId,
            'name' => self::NAME,
            'email' => self::EMAIL,
            'password' => self::HASH,
            'created' => $now,
            'updated' => $now,
        ]);
    }

    private static function execScript(PDO $pdo, string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Script de segurança ausente.');
        }
        $parts = preg_split('/^-- @@statement\s*$/m', $sql) ?: [];
        foreach ($parts as $part) {
            $statement = trim($part);
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
    }
}
