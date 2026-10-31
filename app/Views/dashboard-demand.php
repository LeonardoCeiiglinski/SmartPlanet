<?php

use App\Core\View;
use App\Domain\Sales\ProductDemand;

/** @var list<array{description:string,qty:float,sales:int,total:float}> $rows */
/** @var string $lead */
?>
<div class="table-wrap"><table>
  <thead><tr><th>Produto</th><th class="num">Qtd.</th><th class="num">Vendas</th><th class="num">Total</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="4" class="empty">Nenhuma venda neste período.</td></tr>
  <?php else: foreach ($rows as $row): ?>
    <tr>
      <td><?= View::e($row['description']) ?></td>
      <td class="num"><?= $lead === 'qty' ? '<strong>' . View::e(ProductDemand::qty($row['qty'])) . '</strong>' : View::e(ProductDemand::qty($row['qty'])) ?></td>
      <td class="num"><?= $lead === 'sales' ? '<strong>' . (int) $row['sales'] . '</strong>' : (int) $row['sales'] ?></td>
      <td class="num"><?= View::e(ProductDemand::money($row['total'])) ?></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table></div>
