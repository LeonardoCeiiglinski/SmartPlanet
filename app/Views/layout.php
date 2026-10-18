<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;

$user = Auth::user();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$menu = [
    ['/', 'Painel', 'dashboard.view'],
    ['/pos', 'PDV', 'pos.sell'],
    ['/products', 'Produtos e serviços', 'products.view'],
    ['/labels', 'Etiquetas', 'labels.print'],
    ['/stock', 'Estoque', 'stock.view'],
    ['/purchases', 'Compras', 'purchases.manage'],
    ['/sales', 'Vendas', 'sales.view'],
    ['/cash', 'Caixa', 'cash.open'],
    ['/people?type=customers', 'Clientes', 'customers.manage'],
    ['/people?type=suppliers', 'Fornecedores', 'suppliers.manage'],
    ['/finance', 'Financeiro', 'finance.view'],
    ['/quotes', 'Orçamentos', 'sales.view'],
    ['/reports', 'Relatórios', 'reports.view'],
    ['/users', 'Usuários', 'users.manage'],
    ['/settings', 'Configurações', 'settings.manage'],
    ['/audit', 'Auditoria', 'audit.view'],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= View::e(Csrf::token()) ?>">
  <title><?= View::e($title ?? 'ERP PDV') ?> · ERP PDV</title>
  <link rel="stylesheet" href="/assets/erp.css">
</head>
<body>
  <header class="app-topbar">
    <button class="menu-toggle" type="button" aria-label="Abrir menu" onclick="document.body.classList.toggle('menu-open')">☰</button>
    <strong class="app-name">ERP PDV</strong>
    <span class="top-company"><?= View::e($user['name'] ?? '') ?> · <?= View::e($user['role_name'] ?? '') ?></span>
    <form method="post" action="/logout" class="top-logout">
      <?= Csrf::field() ?>
      <button type="submit">Sair</button>
    </form>
  </header>
  <div class="app-shell">
    <aside class="sidebar">
      <nav>
        <?php foreach ($menu as [$url, $label, $permission]): ?>
          <?php if (Auth::can($permission)): ?>
            <?php $targetPath = parse_url($url, PHP_URL_PATH); ?>
            <a href="<?= View::e($url) ?>" class="<?= $path === $targetPath ? 'active' : '' ?>"><?= View::e($label) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    </aside>
    <main class="main-content">
      <?php foreach (Session::consumeFlash() as $flash): ?>
        <div class="flash <?= View::e($flash['type']) ?>"><?= View::e($flash['message']) ?></div>
      <?php endforeach; ?>
      <?= $content ?>
    </main>
  </div>
</body>
</html>

