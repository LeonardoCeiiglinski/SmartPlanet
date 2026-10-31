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
  <div class="panel-header">Vendas do período</div>
  <div class="panel-body">
    <form class="filters" method="get" action="/">
      <div class="field"><label for="demand-from">De</label><input id="demand-from" type="date" name="from" value="<?= View::e($demand['from']) ?>"></div>
      <div class="field"><label for="demand-to">Até</label><input id="demand-to" type="date" name="to" value="<?= View::e($demand['to']) ?>"></div>
      <button class="primary" type="submit">Filtrar</button>
      <button type="submit" formaction="/painel/pdf">PDF</button>
    </form>
    <p><strong><?= (int) $demand['sales_count'] ?></strong> <?= (int) $demand['sales_count'] === 1 ? 'venda concluída' : 'vendas concluídas' ?> · <strong><?= View::e(\App\Domain\Sales\ProductDemand::money((float) $demand['sales_total'])) ?></strong></p>
    <div class="grid two">
      <div>
        <p><strong>Mais vendidos</strong></p>
        <p class="muted">Pela quantidade vendida.</p>
        <?php $rows = $demand['sold']; $lead = 'qty'; require __DIR__ . '/dashboard-demand.php'; ?>
      </div>
      <div>
        <p><strong>Mais procurados</strong></p>
        <p class="muted">Pela quantidade de vendas em que o produto entrou.</p>
        <?php $rows = $demand['sought']; $lead = 'sales'; require __DIR__ . '/dashboard-demand.php'; ?>
      </div>
    </div>
  </div>
</div>
<div class="panel" style="margin-top:14px">
  <div class="panel-header">Situação do sistema</div>
  <div class="panel-body">
    <p><strong><?= (int) ($stats['products_count'] ?? 0) ?></strong> produtos e serviços ativos.</p>
    <p class="muted">Os indicadores são calculados exclusivamente a partir dos registros do banco. Recibos do PDV não são documentos fiscais.</p>
  </div>
</div>

