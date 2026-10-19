<?php

use App\Core\Auth;
use App\Core\View;

$user = Auth::user();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($title ?? 'Etiquetas') ?> · ERP PDV</title>
  <link rel="stylesheet" href="/assets/erp.css">
  <link rel="stylesheet" href="/legacy/css/styles.css">
  <link rel="stylesheet" href="/assets/labels.css">
  <style id="estilo-pagina">@page { size: 104mm 21mm; margin: 0; }</style>
</head>
<body class="labels-module">
  <header class="labels-topbar">
    <a href="/">← ERP PDV</a>
    <strong>Etiquetas 33 × 21 mm</strong>
    <span><?= View::e($user['name'] ?? '') ?></span>
  </header>
  <?= $content ?>
  <script src="/legacy/js/layout.js"></script>
  <script src="/legacy/js/barcode.js"></script>
  <script src="/legacy/js/render.js"></script>
  <script src="/legacy/js/tspl.js"></script>
  <script src="/assets/labels.js"></script>
</body>
</html>
