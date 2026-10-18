<?php
use App\Core\Csrf;
use App\Core\View;
?>
<div class="page-header"><h1>Compras e recebimentos</h1><a class="button" href="/people?type=suppliers">Fornecedores</a></div>
<div class="panel"><div class="panel-header">Novo pedido de compra</div><div class="panel-body">
<form method="post" action="/purchases/create"><?= Csrf::field() ?>
  <div class="grid three"><div class="field"><label>Fornecedor</label><select name="supplier_id" required><option value="">Selecione</option><?php foreach($suppliers as $supplier): ?><option value="<?= (int)$supplier['id'] ?>"><?= View::e($supplier['name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label>Previsão</label><input type="date" name="expected_at"></div><div class="field"><label>Observações</label><input name="notes"></div></div>
  <div class="table-wrap"><table><thead><tr><th>Produto</th><th>Quantidade</th><th>Custo unitário</th></tr></thead><tbody>
  <?php for($i=0;$i<5;$i++): ?><tr><td><select name="products[]"><option value="">Selecione</option><?php foreach($products as $product): ?><option value="<?= (int)$product['id'] ?>"><?= View::e($product['internal_code'].' · '.$product['description']) ?></option><?php endforeach; ?></select></td>
  <td><input name="quantities[]" inputmode="decimal"></td><td><input name="costs[]" inputmode="decimal"></td></tr><?php endfor; ?>
  </tbody></table></div><button class="primary">Criar pedido</button>
</form></div></div>
<div class="panel"><div class="panel-header">Pedidos</div><div class="panel-body"><div class="table-wrap"><table>
<thead><tr><th>Número</th><th>Fornecedor</th><th>Status</th><th class="num">Total</th><th>Data</th></tr></thead><tbody>
<?php foreach($orders as $item): ?><tr><td><a href="/purchases?id=<?= (int)$item['id'] ?>"><?= View::e($item['order_number']) ?></a></td><td><?= View::e($item['supplier_name']) ?></td><td><span class="badge"><?= View::e($item['status']) ?></span></td><td class="num">R$ <?= number_format((float)$item['total'],2,',','.') ?></td><td><?= View::e($item['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
<?php if($order): ?>
<div class="panel"><div class="panel-header">Receber <?= View::e($order['order_number']) ?> · <?= View::e($order['supplier_name']) ?></div><div class="panel-body">
<form method="post" action="/purchases/receive"><?= Csrf::field() ?><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><input type="hidden" name="idempotency_key" value="<?= View::e(bin2hex(random_bytes(16))) ?>">
<div class="grid two"><div class="field"><label>Local de entrada</label><select name="location_id" required><?php foreach($locations as $location): ?><option value="<?= (int)$location['id'] ?>"><?= View::e($location['name']) ?></option><?php endforeach; ?></select></div><div class="field"><label>Observações do recebimento</label><input name="notes"></div></div>
<div class="table-wrap"><table><thead><tr><th>Produto</th><th class="num">Pedido</th><th class="num">Recebido</th><th>Receber agora</th><th class="num">Custo</th></tr></thead><tbody>
<?php foreach($order['items'] as $item): $remaining=(float)$item['quantity']-(float)$item['received_quantity']; ?><tr><td><?= View::e($item['internal_code'].' · '.$item['description']) ?></td><td class="num"><?= number_format((float)$item['quantity'],4,',','.') ?></td><td class="num"><?= number_format((float)$item['received_quantity'],4,',','.') ?></td><td><input name="quantities[<?= (int)$item['id'] ?>]" value="<?= number_format(max(0,$remaining),4,'.','') ?>" <?= $remaining<=0?'readonly':'' ?>></td><td class="num">R$ <?= number_format((float)$item['unit_cost'],2,',','.') ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php if(!in_array($order['status'],['received','cancelled'],true)): ?><button class="primary">Confirmar recebimento</button><?php endif; ?>
</form></div></div>
<?php endif; ?>
