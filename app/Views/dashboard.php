<?php

use App\Core\View;

$salesCount = (int) ($stats['sales_count'] ?? 0);
$salesTotal = (float) ($stats['sales_total'] ?? 0);
?>
<div class="page-header"><h1>Painel</h1><span><?= View::e(date('d/m/Y H:i')) ?></span></div>
<div class="grid four">
  <div class="stat"><span>Vendas hoje</span><strong><?= number_format($salesCount, 0, ',', '.') ?></strong></div>
  <div class="stat"><span>Total vendido</span><strong>R$ <?= number_format($salesTotal, 2, ',', '.') ?></strong></div>
  <div class="stat"><span>Ticket médio</span><strong>R$ <?= number_format($salesCount ? $salesTotal / $salesCount : 0, 2, ',', '.') ?></strong></div>
  <div class="stat"><span>Caixas abertos</span><strong><?= (int) ($stats['open_cash'] ?? 0) ?></strong></div>
</div>
<div class="panel" style="margin-top:14px">
  <div class="panel-header">Situação do sistema</div>
  <div class="panel-body">
    <p><strong><?= (int) ($stats['products_count'] ?? 0) ?></strong> produtos e serviços ativos.</p>
    <p class="muted">Os indicadores são calculados exclusivamente a partir dos registros do banco.</p>
  </div>
</div>

