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
<div class="grid three" style="margin-top:12px">
  <div class="stat"><span>Estoque baixo</span><strong><?= (int) ($stats['low_stock'] ?? 0) ?></strong></div>
  <div class="stat"><span>A receber</span><strong>R$ <?= number_format((float) ($stats['receivable'] ?? 0), 2, ',', '.') ?></strong></div>
  <div class="stat"><span>A pagar</span><strong>R$ <?= number_format((float) ($stats['payable'] ?? 0), 2, ',', '.') ?></strong></div>
</div>
<div class="panel" style="margin-top:14px">
  <div class="panel-header">Situação do sistema</div>
  <div class="panel-body">
    <p><strong><?= (int) ($stats['products_count'] ?? 0) ?></strong> produtos e serviços ativos.</p>
    <p class="muted">Os indicadores são calculados exclusivamente a partir dos registros do banco. Recibos do PDV não são documentos fiscais.</p>
  </div>
</div>

