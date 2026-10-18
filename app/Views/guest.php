<?php

use App\Core\Session;
use App\Core\View;
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($title ?? 'Acesso') ?> · ERP PDV</title>
  <link rel="stylesheet" href="/assets/erp.css">
</head>
<body class="guest-page">
  <main class="guest-box">
    <h1>ERP PDV</h1>
    <?php foreach (Session::consumeFlash() as $flash): ?>
      <div class="flash <?= View::e($flash['type']) ?>"><?= View::e($flash['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </main>
</body>
</html>

