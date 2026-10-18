<?php

declare(strict_types=1);

return [
    'up' => static function (\PDO $pdo, string $driver): void {
        $flag = $driver === 'mysql' ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0';
        $pdo->exec("ALTER TABLE users ADD COLUMN is_master {$flag}");
        $pdo->exec(
            'UPDATE users SET is_master = 1 WHERE id IN (
                SELECT id FROM (SELECT MIN(id) AS id FROM users GROUP BY company_id) owners
            )'
        );
        $pdo->prepare('INSERT INTO permissions (code, description) VALUES (:code, :description)')
            ->execute(['code' => 'procedures.view', 'description' => 'Consultar os procedimentos da loja']);
        $permissionId = (int) $pdo->lastInsertId();
        $roles = $pdo->query('SELECT id FROM roles')->fetchAll(\PDO::FETCH_COLUMN);
        $grant = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:role, :permission)');
        foreach ($roles as $roleId) {
            $grant->execute(['role' => (int) $roleId, 'permission' => $permissionId]);
        }
    },
    'down' => static function (\PDO $pdo, string $driver): void {
        $pdo->exec("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE code = 'procedures.view')");
        $pdo->exec("DELETE FROM permissions WHERE code = 'procedures.view'");
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users DROP COLUMN is_master');
            return;
        }
        $pdo->exec('ALTER TABLE users DROP COLUMN is_master');
    },
];
