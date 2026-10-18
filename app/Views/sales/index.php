<?php use App\Core\Auth; use App\Core\Csrf; use App\Core\View; ?>
<div class="page-header"><h1>Vendas</h1><a class="button primary" href="/pos">Nova venda</a></div>
<?php if($receipt): ?><section class="receipt panel"><div class="panel-body"><h2>RECIBO NÃO FISCAL</h2><p><?= View::e($receipt['sale_number']) ?> · <?= View::e($receipt['completed_at']) ?><br>Operador: <?= View::e($receipt['operator_name']) ?></p><table><tbody><?php foreach($receipt['items'] as $i): ?><tr><td><?= View::e($i['description']) ?></td><td class="num"><?= number_format((float)$i['quantity'],3,',','.') ?> × R$ <?= number_format((float)$i['unit_price'],2,',','.') ?></td><td class="num">R$ <?= number_format((float)$i['subtotal'],2,',','.') ?></td></tr><?php endforeach ?></tbody></table><p class="num"><strong>Total R$ <?= number_format((float)$receipt['total'],2,',','.') ?></strong></p><p><?php foreach($receipt['payments'] as $p): ?><?= View::e($p['method_name']) ?>: R$ <?= number_format((float)$p['amount'],2,',','.') ?><br><?php endforeach ?></p><button onclick="window.print()">Imprimir</button></div></section><?php endif ?>
<div class="panel"><div class="panel-body table-wrap"><table><thead><tr><th>Número</th><th>Data</th><th>Operador</th><th>Cliente</th><th>Status</th><th class="num">Total</th><th>Ações</th></tr></thead><tbody><?php foreach($sales as $s): ?><tr><td><a href="/sales?receipt=<?= (int)$s['id'] ?>"><?= View::e($s['sale_number']) ?></a></td><td><?= View::e($s['created_at']) ?></td><td><?= View::e($s['operator_name']) ?></td><td><?= View::e($s['customer_name']??'Consumidor final') ?></td><td><?= View::e($s['status']) ?></td><td class="num">R$ <?= number_format((float)$s['total'],2,',','.') ?></td><td><?php if($s['status']==='completed'&&Auth::can('sales.cancel')): ?><form method="post" action="/sales/cancel" class="actions"><?= Csrf::field() ?><input type="hidden" name="sale_id" value="<?= (int)$s['id'] ?>"><input name="reason" minlength="5" placeholder="Justificativa" required><button class="danger small">Cancelar/estornar</button></form><?php endif ?></td></tr><?php endforeach ?></tbody></table></div></div>
<?php if($receipt && $receipt['status']==='completed' && Auth::can('sales.return')): ?>
<form method="post" action="/sales/return" class="panel"><?= Csrf::field() ?>
  <input type="hidden" name="sale_id" value="<?= (int)$receipt['id'] ?>">
  <input type="hidden" name="idempotency_key" value="ret-<?= (int)$receipt['id'] ?>-<?= View::e(bin2hex(random_bytes(4))) ?>">
  <div class="panel-header">Devolução parcial ou total</div>
  <div class="panel-body">
    <table><thead><tr><th></th><th>Item</th><th>Qtd. disponível</th><th>Devolver</th><th>Estoque</th></tr></thead><tbody>
    <?php foreach($receipt['items'] as $i): $available=(float)$i['quantity']-(float)$i['returned_quantity']; if($available<=0) continue; ?>
      <tr>
        <td><input type="checkbox" name="items[<?= (int)$i['id'] ?>][selected]" value="1"></td>
        <td><?= View::e($i['description']) ?><input type="hidden" name="items[<?= (int)$i['id'] ?>][sale_item_id]" value="<?= (int)$i['id'] ?>"></td>
        <td class="num"><?= number_format($available,3,',','.') ?></td>
        <td><input name="items[<?= (int)$i['id'] ?>][quantity]" value="<?= number_format($available,3,'.','') ?>"></td>
        <td><label class="checkbox"><input type="checkbox" name="items[<?= (int)$i['id'] ?>][return_to_stock]" value="1" checked> Devolver ao estoque</label></td>
      </tr>
    <?php endforeach ?></tbody></table>
    <div class="grid two"><div class="field"><label>Motivo</label><input name="reason" minlength="5" required></div><label class="checkbox"><input type="checkbox" name="refund_cash" value="1"> Estornar em dinheiro no caixa aberto</label></div>
    <button class="danger">Confirmar devolução</button>
    <p class="muted">A devolução referencia esta venda. Não é possível devolver quantidade acima do saldo ainda não devolvido.</p>
  </div>
</form>
<?php endif ?>
