<?php

declare(strict_types=1);

return [
    'up' => static function (\PDO $pdo, string $driver): void {
        $permissions = [
            'dashboard.view' => 'Visualizar painel',
            'products.view' => 'Consultar produtos e serviços',
            'products.manage' => 'Cadastrar e alterar produtos e serviços',
            'customers.manage' => 'Gerenciar clientes',
            'suppliers.manage' => 'Gerenciar fornecedores',
            'labels.print' => 'Imprimir etiquetas',
            'stock.view' => 'Consultar estoque',
            'stock.adjust' => 'Realizar ajustes de estoque',
            'purchases.manage' => 'Gerenciar compras e recebimentos',
            'pos.sell' => 'Operar o PDV',
            'sales.view' => 'Consultar vendas',
            'sales.discount' => 'Aplicar descontos',
            'sales.cancel' => 'Cancelar vendas',
            'sales.return' => 'Registrar devoluções',
            'cash.open' => 'Abrir caixa',
            'cash.move' => 'Realizar sangria e suprimento',
            'cash.close' => 'Fechar caixa',
            'finance.view' => 'Consultar financeiro',
            'finance.manage' => 'Gerenciar contas e liquidações',
            'reports.view' => 'Visualizar relatórios',
            'users.manage' => 'Gerenciar usuários e permissões',
            'settings.manage' => 'Gerenciar configurações',
            'audit.view' => 'Consultar auditoria',
        ];
        $stmt = $pdo->prepare('INSERT INTO permissions (code, description) VALUES (:code, :description)');
        foreach ($permissions as $code => $description) {
            $stmt->execute(['code' => $code, 'description' => $description]);
        }
    },
    'down' => static function (\PDO $pdo, string $driver): void {
        $pdo->exec('DELETE FROM role_permissions');
        $pdo->exec('DELETE FROM permissions');
    },
];

