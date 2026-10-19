<?php

use App\Core\Session;
use App\Core\View;
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($title ?? 'Acesso') ?> · SmartPlanet</title>
  <link rel="icon" type="image/png" href="/favicon.png">
  <link rel="apple-touch-icon" href="/favicon.png">
  <link rel="stylesheet" href="/assets/erp.css">
  <link rel="stylesheet" href="/assets/login.css">
  <meta name="referrer" content="no-referrer">
</head>
<body class="guest-page">
  <main class="vault">
    <img class="vault-logo" src="/assets/logo-smartplanet.png" alt="SmartPlanet, a Boutique do Celular">
    <?php foreach (Session::consumeFlash() as $flash): ?>
      <div class="flash <?= View::e($flash['type']) ?>"><?= View::e($flash['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </main>
  <script<?= \App\Core\Security::nonceAttribute() ?> src="/assets/login.js?v=<?= (int) (@filemtime(dirname(__DIR__, 2) . '/public/assets/login.js') ?: time()) ?>"></script>
</body>
</html>

