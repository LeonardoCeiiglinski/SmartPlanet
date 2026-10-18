<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;

$user = Auth::user();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$icon = static function (string $name): string {
    $paths = [
        'painel' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
        'pdv' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><path d="M6 12h4M6 16h8"/>',
        'produtos' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/>',
        'etiquetas' => '<path d="M20.6 13.4 12.7 21.3a1 1 0 0 1-1.4 0L2.7 12.7a1 1 0 0 1 0-1.4L11.6 2.4A2 2 0 0 1 13 2h7a2 2 0 0 1 2 2v7a2 2 0 0 1-.4 1.4z"/><circle cx="16.5" cy="7.5" r="1.5"/>',
        'estoque' => '<path d="M3 9 12 4l9 5-9 5-9-5z"/><path d="M3 14l9 5 9-5"/><path d="M3 19l9 5 9-5"/>',
        'compras' => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h8.6a2 2 0 0 0 2-1.5L22 8H6"/>',
        'vendas' => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/>',
        'caixa' => '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/><circle cx="16" cy="14" r="1.4"/>',
        'clientes' => '<circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/>',
        'fornecedores' => '<path d="M3 17h13V7H3z"/><path d="M16 10h4l2 4v3h-6z"/><circle cx="6.5" cy="17.5" r="1.8"/><circle cx="18.5" cy="17.5" r="1.8"/>',
        'financeiro' => '<path d="M12 2v20"/><path d="M17 6.5c-.8-1.2-2.3-2-4.5-2-3 0-5 1.6-5 4s2 3.4 5 4.2c3 .8 5 2 5 4.3s-2.2 4-5.2 4c-2.4 0-4.1-.9-5-2.3"/>',
        'orcamentos' => '<path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h6"/>',
        'relatorios' => '<path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/>',
        'funcionarios' => '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 19a6.5 6.5 0 0 1 13 0"/><circle cx="17" cy="9" r="2.4"/><path d="M16 19a5 5 0 0 1 6-4.8"/>',
        'procedimentos' => '<path d="M8 6h13M8 12h13M8 18h9"/><path d="M3 6l1.2 1.2L6.2 5M3 12l1.2 1.2L6.2 11"/>',
        'configuracoes' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.3.6.9 1 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'auditoria' => '<path d="M12 22s8-4 8-10V6l-8-4-8 4v6c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
    ];
    $inner = $paths[$name] ?? $paths['painel'];
    return '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</svg>';
};
$menu = [
    ['/', 'Painel', 'dashboard.view', 'painel'],
    ['/pos', 'PDV', 'pos.sell', 'pdv'],
    ['/products', 'Produtos e serviços', 'products.view', 'produtos'],
    ['/labels', 'Etiquetas', 'labels.print', 'etiquetas'],
    ['/stock', 'Estoque', 'stock.view', 'estoque'],
    ['/purchases', 'Compras', 'purchases.manage', 'compras'],
    ['/sales', 'Vendas', 'sales.view', 'vendas'],
    ['/cash', 'Caixa', 'cash.open', 'caixa'],
    ['/people?type=customers', 'Clientes', 'customers.manage', 'clientes'],
    ['/people?type=suppliers', 'Fornecedores', 'suppliers.manage', 'fornecedores'],
    ['/finance', 'Financeiro', 'finance.view', 'financeiro'],
    ['/quotes', 'Orçamentos', 'sales.view', 'orcamentos'],
    ['/reports', 'Relatórios', 'reports.view', 'relatorios'],
    ['/users', 'Funcionários', 'users.manage', 'funcionarios'],
    ['/procedures', 'Procedimentos', 'procedures.view', 'procedimentos'],
    ['/settings', 'Configurações', 'settings.manage', 'configuracoes'],
    ['/audit', 'Auditoria', 'audit.view', 'auditoria'],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= View::e(Csrf::token()) ?>">
  <title><?= View::e($title ?? 'SmartPlanet') ?> · SmartPlanet</title>
  <link rel="icon" type="image/png" href="/favicon.png">
  <link rel="apple-touch-icon" href="/favicon.png">
  <link rel="stylesheet" href="/assets/erp.css">
  <?= $headExtra ?? '' ?>
</head>
<body<?= !empty($bodyClass) ? ' class="' . View::e($bodyClass) . '"' : '' ?>>
  <header class="app-topbar">
    <button class="menu-toggle" type="button" aria-label="Abrir menu" onclick="document.body.classList.toggle('menu-open')">☰</button>
    <a class="brand" href="/">
      <img src="/assets/logo-smartplanet.png" alt="">
      <span><strong>SmartPlanet</strong><small>a Boutique do Celular</small></span>
    </a>
    <span class="top-company"><?= View::e($user['name'] ?? '') ?> · <?= View::e($user['role_name'] ?? '') ?></span>
    <form method="post" action="/logout" class="top-logout">
      <?= Csrf::field() ?>
      <button type="submit">Sair</button>
    </form>
  </header>
  <div class="app-shell">
    <aside class="sidebar">
      <nav>
        <?php foreach ($menu as [$url, $label, $permission, $iconName]): ?>
          <?php if (($url === '/users' ? Auth::isMaster() : Auth::can($permission))): ?>
            <?php $targetPath = parse_url($url, PHP_URL_PATH); ?>
            <a href="<?= View::e($url) ?>" class="<?= $path === $targetPath ? 'active' : '' ?>"><?= $icon($iconName) ?><span><?= View::e($label) ?></span></a>
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
  <div id="shell-end"><?= $bodyEnd ?? '' ?></div>
  <div id="shell-progress"></div>
  <script src="/assets/forms.js?v=<?= (int) (@filemtime(dirname(__DIR__, 2) . '/public/assets/forms.js') ?: time()) ?>"></script>
  <script src="/assets/shell.js?v=<?= (int) (@filemtime(dirname(__DIR__, 2) . '/public/assets/shell.js') ?: time()) ?>"></script>
</body>
</html>

